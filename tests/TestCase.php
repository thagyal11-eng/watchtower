<?php

namespace Watchtower\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Watchtower\WatchtowerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            WatchtowerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        // In-memory sqlite so migrations + reads run without external services.
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $config->set('watchtower.enabled', true);
        // Write synchronously in tests so assertions see rows immediately.
        $config->set('watchtower.writes.after_response', false);
        $config->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
