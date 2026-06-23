<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Commands;

use Illuminate\Console\Command;
use SwissEid\LaravelSwissEid\Models\EidVerification;

class CleanupCommand extends Command
{
    protected $signature = 'swiss-eid:cleanup
                            {--days=7 : Delete records older than this many days}
                            {--dry-run : Show how many records would be deleted without deleting them}';

    protected $description = 'Delete eID verification records whose TTL elapsed more than a given number of days ago (regardless of outcome)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($days);

        // The cutoff (now − days) already implies the record's TTL has elapsed,
        // so a separate expired() filter would be redundant.
        $query = EidVerification::query()
            ->where('expires_at', '<', $cutoff);

        $count = $query->count();

        if ($dryRun) {
            $this->info("[Dry run] Would delete {$count} expired verification record(s) older than {$days} days.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} expired verification record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
