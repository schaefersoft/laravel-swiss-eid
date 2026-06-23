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

it('defaults the SVG dimensions to 300 pixels', function (): void {
    $svg = (new QrCodeGenerator)->svg('openid-vc://example');

    expect($svg)->toContain('width="300"')
        ->and($svg)->toContain('height="300"');
});

it('applies the requested pixel size to the SVG dimensions', function (): void {
    $svg = (new QrCodeGenerator)->svg('openid-vc://example', 150);

    expect($svg)->toContain('width="150"')
        ->and($svg)->toContain('height="150"');
});
