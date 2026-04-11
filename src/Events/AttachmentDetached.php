<?php

namespace Phunky\LaravelMessagingAttachments\Events;

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;

class AttachmentDetached extends BroadcastableMessagingEvent
{
    public function __construct(
        public Message $message,
        public Messageable $messageable,
        public int|string $attachmentId,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'messaging.attachment.detached';
    }
}
