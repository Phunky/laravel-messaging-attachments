<?php

namespace Phunky\LaravelMessagingAttachments\Events;

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingAttachments\Attachment;

class AttachmentAttached extends BroadcastableMessagingEvent
{
    public const BROADCAST_NAME = 'messaging.attachment.attached';

    public function __construct(
        public Attachment $attachment,
        public Message $message,
        public Messageable $messageable,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_NAME;
    }

    /**
     * @return array{conversation_id: int|string, message_id: int|string, attachment_id: int|string, messageable_type: string, messageable_id: int|string}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->getAttribute('conversation_id'),
            'message_id' => $this->message->getKey(),
            'attachment_id' => $this->attachment->getKey(),
            'messageable_type' => $this->messageable->getMorphClass(),
            'messageable_id' => $this->messageable->getKey(),
        ];
    }
}
