<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Events;

use SwissEid\LaravelSwissEid\Models\EidVerification;

class VerificationExpired
{
    public function __construct(
        public readonly EidVerification $verification,
    ) {}
}
