<?php

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\Attachment;
use Phunky\LaravelMessagingAttachments\AttachmentService;
use Phunky\LaravelMessagingAttachments\Events\AttachmentAttached;
use Phunky\LaravelMessagingAttachments\Events\AttachmentDetached;
use Phunky\LaravelMessagingAttachments\Exceptions\AttachmentException;
use Phunky\LaravelMessagingAttachments\Tests\Fixtures\User;

function attachmentUsers(): array
{
    return [
        User::create(['name' => 'Alice']),
        User::create(['name' => 'Bob']),
    ];
}

describe('attach', function () {
    it('attaches attachment metadata for the sender', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $attachment = app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'uploads/1/photo.jpg',
            'filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'public',
        ]);

        expect($attachment)->toBeInstanceOf(Attachment::class)
            ->and($attachment->type)->toBe('image')
            ->and($attachment->path)->toBe('uploads/1/photo.jpg')
            ->and($attachment->filename)->toBe('photo.jpg')
            ->and($attachment->order)->toBe(0)
            ->and((int) $attachment->message_id)->toBe((int) $message->getKey())
            ->and((int) $attachment->conversation_id)->toBe((int) $conversation->getKey());
    });

    it('dispatches AttachmentAttached', function () {
        Event::fake([AttachmentAttached::class]);

        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        app(AttachmentService::class)->attach($message, $a, [
            'type' => 'document',
            'path' => 'files/doc.pdf',
            'filename' => 'doc.pdf',
        ]);

        Event::assertDispatched(AttachmentAttached::class);
    });

    it('rejects missing required fields', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $service = app(AttachmentService::class);

        expect(fn () => $service->attach($message, $a, [
            'path' => 'x',
            'filename' => 'f',
        ]))->toThrow(AttachmentException::class, 'type');

        expect(fn () => $service->attach($message, $a, [
            'type' => 'image',
            'filename' => 'f',
        ]))->toThrow(AttachmentException::class, 'path');

        expect(fn () => $service->attach($message, $a, [
            'type' => 'image',
            'path' => 'p',
        ]))->toThrow(AttachmentException::class, 'filename');
    });
});

describe('attachMany', function () {
    it('attaches multiple items with auto-incrementing order', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $collection = app(AttachmentService::class)->attachMany($message, $a, [
            [
                'type' => 'image',
                'path' => 'a.jpg',
                'filename' => 'a.jpg',
            ],
            [
                'type' => 'video',
                'path' => 'b.mp4',
                'filename' => 'b.mp4',
            ],
        ]);

        expect($collection)->toHaveCount(2)
            ->and($collection[0]->order)->toBe(0)
            ->and($collection[1]->order)->toBe(1);
    });

    it('returns empty collection for empty items', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $collection = app(AttachmentService::class)->attachMany($message, $a, []);

        expect($collection)->toHaveCount(0);
    });
});

describe('detach', function () {
    it('removes attachments and dispatches AttachmentDetached', function () {
        Event::fake([AttachmentDetached::class]);

        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $service = app(AttachmentService::class);
        $row = $service->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);
        $id = $row->getKey();

        $service->detach($message, $a, $row);

        expect(Attachment::query()->where('message_id', $message->getKey())->count())->toBe(0);
        Event::assertDispatched(AttachmentDetached::class, function (AttachmentDetached $e) use ($message, $id): bool {
            return (string) $e->message->getKey() === (string) $message->getKey()
                && (string) $e->attachmentId === (string) $id;
        });
    });

    it('throws when attachment belongs to another message', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $m1 = $messaging->sendMessage($conversation, $a, 'one');
        $m2 = $messaging->sendMessage($conversation, $a, 'two');

        $service = app(AttachmentService::class);
        $row = $service->attach($m1, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        expect(fn () => $service->detach($m2, $a, $row))
            ->toThrow(AttachmentException::class, 'does not belong');
    });
});

describe('getAttachments', function () {
    it('returns attachments ordered by order then id', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $service = app(AttachmentService::class);
        $service->attach($message, $a, [
            'type' => 'image',
            'path' => 'z.jpg',
            'filename' => 'z.jpg',
            'order' => 1,
        ]);
        $service->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
            'order' => 0,
        ]);

        $all = $service->getAttachments($message);

        expect($all->first()->filename)->toBe('a.jpg')
            ->and($all->last()->filename)->toBe('z.jpg');
    });
});

describe('getAttachmentsForConversation', function () {
    it('returns attachments for all messages in the conversation ordered by message sent_at', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);

        $m1 = $messaging->sendMessage($conversation, $a, 'one');
        $service = app(AttachmentService::class);
        $service->attach($m1, $a, [
            'type' => 'image',
            'path' => 'first.jpg',
            'filename' => 'first.jpg',
        ]);

        $m2 = $messaging->sendMessage($conversation, $a, 'two');
        $service->attach($m2, $a, [
            'type' => 'image',
            'path' => 'second.jpg',
            'filename' => 'second.jpg',
        ]);

        $all = $service->getAttachmentsForConversation($conversation);

        expect($all)->toHaveCount(2)
            ->and((int) $all[0]->conversation_id)->toBe((int) $conversation->getKey())
            ->and($all[0]->filename)->toBe('second.jpg')
            ->and($all[1]->filename)->toBe('first.jpg');
    });

    it('accepts a conversation resolved from config model', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        $c = Conversation::query()->findOrFail($conversation->getKey());
        $all = app(AttachmentService::class)->getAttachmentsForConversation($c);

        expect($all)->toHaveCount(1)
            ->and($all[0]->filename)->toBe('a.jpg');
    });
});

describe('authorization', function () {
    it('blocks non-senders from detaching', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $row = app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        expect(fn () => app(AttachmentService::class)->detach($message, $b, $row))
            ->toThrow(CannotMessageException::class);
    });

    it('blocks non-senders from attaching', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        expect(fn () => app(AttachmentService::class)->attach($message, $b, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]))->toThrow(CannotMessageException::class);
    });

    it('blocks strangers from attaching', function () {
        [$a, $b] = attachmentUsers();
        $stranger = User::create(['name' => 'Zed']);
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        expect(fn () => app(AttachmentService::class)->attach($message, $stranger, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]))->toThrow(CannotMessageException::class);
    });
});

describe('message delete', function () {
    it('removes attachments when the message is soft-deleted', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');
        $messageId = (int) $message->getKey();

        app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        expect(Attachment::query()->where('message_id', $messageId)->count())->toBe(1);

        $message->delete();

        expect(Attachment::query()->where('message_id', $messageId)->count())->toBe(0);
    });

    it('removes attachments when the message is force-deleted', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');
        $messageId = (int) $message->getKey();

        app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        expect(Attachment::query()->where('message_id', $messageId)->count())->toBe(1);

        $message->forceDelete();

        expect(Attachment::query()->where('message_id', $messageId)->count())->toBe(0);
    });
});

describe('broadcasting', function () {
    it('exposes stable top-level attachment payload ids', function () {
        Config::set('messaging.broadcasting.enabled', true);
        Config::set('messaging.broadcasting.channel_prefix', 'messaging');

        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');
        $attachment = app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        $attached = new AttachmentAttached($attachment, $message, $a);
        $detached = new AttachmentDetached($message, $a, $attachment->getKey());

        expect($attached)->toBeInstanceOf(ShouldBroadcast::class)
            ->toBeInstanceOf(ShouldDispatchAfterCommit::class)
            ->and($attached->broadcastWhen())->toBeTrue()
            ->and($attached->broadcastOn()[0]->name)->toBe('private-messaging.conversation.'.$conversation->getKey())
            ->and($attached->broadcastAs())->toBe(AttachmentAttached::BROADCAST_NAME)
            ->and($attached->broadcastWith()['conversation_id'])->toBe($conversation->getKey())
            ->and($attached->broadcastWith()['message_id'])->toBe($message->getKey())
            ->and($attached->broadcastWith()['attachment_id'])->toBe($attachment->getKey())
            ->and($detached->broadcastAs())->toBe(AttachmentDetached::BROADCAST_NAME)
            ->and($detached->broadcastWith()['attachment_id'])->toBe($attachment->getKey());
    });
});

describe('Message macro', function () {
    it('exposes attachments relationship', function () {
        [$a, $b] = attachmentUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        app(AttachmentService::class)->attach($message, $a, [
            'type' => 'image',
            'path' => 'a.jpg',
            'filename' => 'a.jpg',
        ]);

        $message->refresh();

        expect($message->attachments())->toBeInstanceOf(HasMany::class)
            ->and($message->attachments()->count())->toBe(1);
    });
});
