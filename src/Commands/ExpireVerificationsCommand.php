<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Commands;

use Illuminate\Console\Command;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Events\VerificationExpired;
use SwissEid\LaravelSwissEid\Models\EidVerification;

class ExpireVerificationsCommand extends Command
{
    protected $signature = 'swiss-eid:expire';

    protected $description = 'Mark pending verifications whose TTL has passed as expired and dispatch the VerificationExpired event';

    public function handle(): int
    {
        $verifications = EidVerification::query()->pending()->expired()->get();

        foreach ($verifications as $verification) {
            $verification->update(['state' => VerificationState::Expired]);
            $verification->refresh();

            event(new VerificationExpired($verification));
        }

        $count = $verifications->count();
        $this->info("Marked {$count} verification(s) as expired.");

        return self::SUCCESS;
    }
}
