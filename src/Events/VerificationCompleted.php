<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid\Events;

use SchaeferSoft\SwissEid\Models\EidVerification;

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
