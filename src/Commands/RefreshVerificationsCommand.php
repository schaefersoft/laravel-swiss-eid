<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid\Commands;

use Illuminate\Console\Command;
use SchaeferSoft\SwissEid\Exceptions\SwissEidException;
use SchaeferSoft\SwissEid\Models\EidVerification;
use SchaeferSoft\SwissEid\SwissEidManager;

class RefreshVerificationsCommand extends Command
{
    protected $signature = 'swiss-eid:refresh {id? : Local UUID or verifier ID of a single verification}';

    protected $description = 'Pull the current state of pending verifications from the swiyu verifier';

    public function handle(SwissEidManager $manager): int
    {
        $id = $this->argument('id');

        $ids = is_string($id)
            ? [$id]
            : EidVerification::query()->pending()->pluck('id')->all();

        $failures = 0;

        foreach ($ids as $verificationId) {
            try {
                $result = $manager->refresh((string) $verificationId);
                $this->line("{$verificationId}: {$result->state->value}");
            } catch (SwissEidException $e) {
                $failures++;
                $this->error("{$verificationId}: {$e->getMessage()}");
            }
        }

        $this->info('Refreshed '.(count($ids) - $failures).' verification(s).');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
