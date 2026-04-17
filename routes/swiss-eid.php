<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SwissEid\LaravelSwissEid\Controllers\VerificationStatusController;
use SwissEid\LaravelSwissEid\Controllers\WebhookController;
use SwissEid\LaravelSwissEid\Middleware\VerifyWebhookApiKey;

Route::middleware('api')->group(function (): void {
    Route::post(
        config('swiss-eid.webhook.path', '/swiss-eid/webhook'),
        WebhookController::class,
    )
        ->middleware(VerifyWebhookApiKey::class)
        ->name('swiss-eid.webhook');

    if ((bool) config('swiss-eid.polling.enabled', true)) {
        Route::get(
            config('swiss-eid.polling.route_path', '/swiss-eid/status').'/{verification}',
            VerificationStatusController::class,
        )->name('swiss-eid.status');
    }
});
