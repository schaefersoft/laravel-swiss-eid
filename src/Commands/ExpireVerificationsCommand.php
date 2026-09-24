<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid\Commands;

use Illuminate\Console\Command;
use SchaeferSoft\SwissEid\Enums\VerificationState;
use SchaeferSoft\SwissEid\Events\VerificationExpired;
use SchaeferSoft\SwissEid\Models\EidVerification;

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
