<?php

namespace Phunky\LaravelMessagingAttachments;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\Events\AttachmentAttached;
use Phunky\LaravelMessagingAttachments\Events\AttachmentDetached;
use Phunky\LaravelMessagingAttachments\Exceptions\AttachmentException;

class AttachmentService
{
    public function __construct(
        protected MessagingService $messaging,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attach(Message $message, Messageable $sender, array $attributes): Attachment
    {
        $this->messaging->assertMessageSender($message, $sender);
        $this->validateItem($attributes);

        $attrs = $this->normalizeAttributes($attributes);

        if (! array_key_exists('order', $attrs)) {
            $attrs['order'] = $this->nextOrder($message);
        }

        /** @var Attachment $attachment */
        $attachment = Attachment::query()->create(array_merge(
            [
                'message_id' => $message->getKey(),
                'conversation_id' => $this->conversationIdFromMessage($message),
            ],
            $attrs,
        ));

        event(new AttachmentAttached($attachment, $message, $sender));

        return $attachment;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Attachment>
     */
    public function attachMany(Message $message, Messageable $sender, array $items): Collection
    {
        $this->messaging->assertMessageSender($message, $sender);

        if ($items === []) {
            return new Collection;
        }

        return DB::transaction(function () use ($message, $sender, $items): Collection {
            $order = $this->nextOrder($message);
            $created = new Collection;

            foreach ($items as $item) {
                $this->validateItem($item);
                $attrs = $this->normalizeAttributes($item);

                if (! array_key_exists('order', $attrs)) {
                    $attrs['order'] = $order;
                    $order++;
                }

                /** @var Attachment $attachment */
                $attachment = Attachment::query()->create(array_merge(
                    [
                        'message_id' => $message->getKey(),
                        'conversation_id' => $this->conversationIdFromMessage($message),
                    ],
                    $attrs,
                ));

                event(new AttachmentAttached($attachment, $message, $sender));

                $created->push($attachment);
            }

            return $created;
        });
    }

    public function detach(Message $message, Messageable $sender, Attachment $attachment): void
    {
        $this->messaging->assertMessageSender($message, $sender);

        if ((string) $attachment->message_id !== (string) $message->getKey()) {
            throw new AttachmentException('Attachment does not belong to this message.');
        }

        $id = $attachment->getKey();

        $attachment->delete();

        event(new AttachmentDetached($message, $sender, $id));
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(Message $message): Collection
    {
        return Attachment::query()
            ->where('message_id', $message->getKey())
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * All attachments in a conversation, ordered by the parent message’s `sent_at` (newest message first), then attachment `order` and `id`.
     *
     * @return Collection<int, Attachment>
     */
    public function getAttachmentsForConversation(Conversation $conversation): Collection
    {
        $attachmentsTable = messaging_table('attachments');
        $messageTable = messaging_table('messages');

        return Attachment::query()
            ->select($attachmentsTable.'.*')
            ->from($attachmentsTable)
            ->join($messageTable, $messageTable.'.id', '=', $attachmentsTable.'.message_id')
            ->where($attachmentsTable.'.conversation_id', $conversation->getKey())
            ->orderByDesc($messageTable.'.sent_at')
            ->orderByDesc($messageTable.'.id')
            ->orderBy($attachmentsTable.'.order')
            ->orderBy($attachmentsTable.'.id')
            ->get();
    }

    protected function conversationIdFromMessage(Message $message): int|string
    {
        $id = $message->getAttribute('conversation_id');

        if ($id === null) {
            throw new AttachmentException('Message is missing conversation_id.');
        }

        return $id;
    }

    protected function nextOrder(Message $message): int
    {
        $max = Attachment::query()
            ->where('message_id', $message->getKey())
            ->max('order');

        if ($max === null) {
            return 0;
        }

        return (int) $max + 1;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function validateItem(array $item): void
    {
        foreach (['type', 'path', 'filename'] as $key) {
            if (! isset($item[$key]) || ! is_string($item[$key]) || trim($item[$key]) === '') {
                throw new AttachmentException("Attachment attribute [{$key}] is required and must be a non-empty string.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $attributes): array
    {
        $out = [
            'type' => trim((string) $attributes['type']),
            'path' => trim((string) $attributes['path']),
            'filename' => trim((string) $attributes['filename']),
        ];

        if (array_key_exists('disk', $attributes) && $attributes['disk'] !== null) {
            $out['disk'] = (string) $attributes['disk'];
        }

        if (array_key_exists('url', $attributes) && $attributes['url'] !== null) {
            $out['url'] = (string) $attributes['url'];
        }

        if (array_key_exists('mime_type', $attributes) && $attributes['mime_type'] !== null) {
            $out['mime_type'] = (string) $attributes['mime_type'];
        }

        if (array_key_exists('size', $attributes) && $attributes['size'] !== null) {
            $out['size'] = (int) $attributes['size'];
        }

        if (array_key_exists('order', $attributes) && $attributes['order'] !== null) {
            $out['order'] = (int) $attributes['order'];
        }

        if (array_key_exists('meta', $attributes) && $attributes['meta'] !== null) {
            $meta = $attributes['meta'];
            if (! is_array($meta)) {
                throw new AttachmentException('Attachment attribute [meta] must be an array or null.');
            }
            $out['meta'] = $meta === [] ? null : $meta;
        }

        return $out;
    }
}
