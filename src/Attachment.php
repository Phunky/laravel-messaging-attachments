<?php

namespace Phunky\LaravelMessagingAttachments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;

/**
 * @property int|string $id
 * @property int|string $message_id
 * @property int|string $conversation_id
 * @property string $type
 * @property string|null $disk
 * @property string $path
 * @property string|null $url
 * @property string $filename
 * @property string|null $mime_type
 * @property int|null $size
 * @property int $order
 * @property array<string, mixed>|null $meta
 * @property-read Message $message
 * @property-read Conversation $conversation
 */
class Attachment extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = messaging_table('attachments');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(config('messaging.models.message'), 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(config('messaging.models.conversation'), 'conversation_id');
    }
}
