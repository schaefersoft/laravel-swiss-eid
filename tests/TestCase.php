<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use SwissEid\LaravelSwissEid\SwissEidServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SwissEidServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        config()->set('swiss-eid.verifier.base_url', 'http://localhost:8083');
        config()->set('swiss-eid.webhook.api_key', 'test-webhook-secret');
        config()->set('swiss-eid.credentials.type', 'betaid-sdjwt');
        config()->set('swiss-eid.verification_ttl', 300);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
