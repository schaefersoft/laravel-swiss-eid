<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\DTOs;

use Carbon\Carbon;
use SwissEid\LaravelSwissEid\QrCodeGenerator;

class PendingVerification
{
    public function __construct(
        /** Our internal DB UUID. */
        public readonly string $id,
        /** The ID assigned by the swiyu verifier. */
        public readonly string $verifierId,
        /** Universal deeplink for the Swiss Wallet app. */
        public readonly string $deeplink,
        /** Full URL to the verifier's presentation request. */
        public readonly string $verificationUrl,
        /** Current state string (always 'pending' at creation). */
        public readonly string $state,
        /** When this verification request expires. */
        public readonly Carbon $expiresAt,
    ) {}

    /**
     * Generate an SVG QR code for the deeplink.
     *
     * @param  int  $size  Size in pixels (width/height).
     */
    public function qrCode(int $size = 300): string
    {
        return app(QrCodeGenerator::class)->svg($this->deeplink, $size);
    }

    /**
     * Generate a base64-encoded data URI of the SVG QR code.
     *
     * @param  int  $size  Size in pixels (width/height).
     */
    public function qrCodeDataUri(int $size = 300): string
    {
        return app(QrCodeGenerator::class)->dataUri($this->deeplink, $size);
    }

    /**
     * Whether this pending verification has already expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    /**
     * URL to poll for the verification status.
     */
    public function statusUrl(): string
    {
        return route('swiss-eid.status', ['verification' => $this->id]);
    }
}
