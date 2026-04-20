<?php

declare(strict_types=1);

use SwissEid\LaravelSwissEid\QrCodeGenerator;

it('generates an inline SVG containing the encoded data', function (): void {
    $svg = (new QrCodeGenerator)->svg('openid-vc://example');

    expect($svg)->toContain('<svg')
        ->and($svg)->toContain('</svg>');
});

it('returns a base64 data URI prefixed with the SVG mime type', function (): void {
    $uri = (new QrCodeGenerator)->dataUri('openid-vc://example');

    expect($uri)->toStartWith('data:image/svg+xml;base64,');

    $payload = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
    expect($payload)->not->toBeFalse()
        ->and($payload)->toContain('<svg');
});
