<?php

namespace Phunky\LaravelMessagingAttachments\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Phunky\LaravelMessaging\MessagingServiceProvider;
use Phunky\LaravelMessagingAttachments\AttachmentExtension;

abstract class AttachmentTestCase extends Orchestra
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('messaging.extensions', [
            AttachmentExtension::class,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagingServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
