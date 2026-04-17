<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

use Illuminate\Support\ServiceProvider;
use SwissEid\LaravelSwissEid\Commands\CleanupCommand;
use SwissEid\LaravelSwissEid\Commands\InstallCommand;
use SwissEid\LaravelSwissEid\Commands\TestConnectionCommand;

class SwissEidServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/swiss-eid.php',
            'swiss-eid',
        );

        $this->app->singleton(VerifierClient::class, function (): VerifierClient {
            return new VerifierClient(config('swiss-eid'));
        });

        $this->app->singleton(SwissEidManager::class, function (): SwissEidManager {
            return new SwissEidManager(
                client: $this->app->make(VerifierClient::class),
                config: config('swiss-eid'),
            );
        });

        $this->app->alias(SwissEidManager::class, 'swiss-eid');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/swiss-eid.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/swiss-eid.php' => config_path('swiss-eid.php'),
            ], 'swiss-eid-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'swiss-eid-migrations');

            $this->commands([
                InstallCommand::class,
                TestConnectionCommand::class,
                CleanupCommand::class,
            ]);
        }
    }
}
