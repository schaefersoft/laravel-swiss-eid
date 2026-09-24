<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid\Events;

use SchaeferSoft\SwissEid\Models\EidVerification;

class VerificationExpired
{
    public function __construct(
        public readonly EidVerification $verification,
    ) {}
}
