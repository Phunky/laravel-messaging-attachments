<?php

namespace Phunky\LaravelMessagingAttachments;

use Illuminate\Contracts\Foundation\Application;
use Phunky\LaravelMessaging\Contracts\MessagingExtension;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;

class AttachmentExtension implements MessagingExtension
{
    public function register(Application $app): void
    {
        $app->singleton(AttachmentService::class, fn (Application $app): AttachmentService => new AttachmentService(
            $app->make(MessagingService::class),
        ));
    }

    public function boot(Application $app): void
    {
        $migrationDir = dirname(__DIR__).'/database/migrations';

        $app->afterResolving('migrator', function ($migrator) use ($migrationDir): void {
            $migrator->path($migrationDir);
        });

        if (! Message::hasMacro('attachments')) {
            Message::macro('attachments', function () {
                /** @var Message $this */
                return $this->hasMany(Attachment::class, 'message_id');
            });
        }

        Message::deleted(function (Message $message): void {
            Attachment::query()->where('message_id', $message->getKey())->delete();
        });
    }
}
