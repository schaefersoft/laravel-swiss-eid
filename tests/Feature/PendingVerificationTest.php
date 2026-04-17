<?php

declare(strict_types=1);

use Carbon\Carbon;
use SwissEid\LaravelSwissEid\DTOs\PendingVerification;

function makePending(?Carbon $expiresAt = null): PendingVerification
{
    return new PendingVerification(
        id: 'local-uuid-123',
        verifierId: 'verifier-uuid-456',
        deeplink: 'openid-vc://example',
        verificationUrl: 'http://localhost:8083/verify/456',
        state: 'pending',
        expiresAt: $expiresAt ?? Carbon::now()->addMinutes(5),
    );
}

it('produces an SVG QR code for the deeplink', function (): void {
    $svg = makePending()->qrCode();

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('</svg>');
});

it('produces a base64 data URI for the QR code', function (): void {
    $uri = makePending()->qrCodeDataUri();

    expect($uri)->toStartWith('data:image/svg+xml;base64,');
});

it('reports isExpired() correctly based on expiresAt', function (): void {
    expect(makePending(Carbon::now()->subMinute())->isExpired())->toBeTrue();
    expect(makePending(Carbon::now()->addMinute())->isExpired())->toBeFalse();
});

it('builds the polling status URL using the named route', function (): void {
    $url = makePending()->statusUrl();

    expect($url)->toContain('/swiss-eid/status/local-uuid-123');
});
