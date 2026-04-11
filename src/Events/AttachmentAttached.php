<?php

namespace Phunky\LaravelMessagingAttachments\Events;

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingAttachments\Attachment;

class AttachmentAttached extends BroadcastableMessagingEvent
{
    public function __construct(
        public Attachment $attachment,
        public Message $message,
        public Messageable $messageable,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'messaging.attachment.attached';
    }
}
