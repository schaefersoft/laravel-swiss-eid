<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Events;

use SwissEid\LaravelSwissEid\Models\EidVerification;

class VerificationCompleted
{
    /** @var array<string, mixed>|null */
    public readonly ?array $credentialData;

    public function __construct(
        public readonly EidVerification $verification,
    ) {
        $this->credentialData = $verification->credential_data;
    }
}
