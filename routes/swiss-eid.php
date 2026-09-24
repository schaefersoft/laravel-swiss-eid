<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SchaeferSoft\SwissEid\Controllers\VerificationStatusController;
use SchaeferSoft\SwissEid\Controllers\WebhookController;
use SchaeferSoft\SwissEid\Middleware\VerifyWebhookApiKey;

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
        )
            ->middleware('throttle:'.config('swiss-eid.polling.rate_limit', '60,1'))
            ->name('swiss-eid.status');
    }
});
