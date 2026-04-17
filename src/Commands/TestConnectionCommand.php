<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Commands;

use Illuminate\Console\Command;
use SwissEid\LaravelSwissEid\Exceptions\SwissEidException;
use SwissEid\LaravelSwissEid\Exceptions\VerifierConnectionException;
use SwissEid\LaravelSwissEid\VerifierClient;

class TestConnectionCommand extends Command
{
    protected $signature = 'swiss-eid:test-connection';

    protected $description = 'Test the connection to the configured swiyu verifier';

    public function handle(VerifierClient $client): int
    {
        $baseUrl = config('swiss-eid.verifier.base_url');
        $this->info("Testing connection to: {$baseUrl}");

        try {
            // Attempt to list verifications as a health check
            // A 200 or 404 both indicate the verifier is reachable
            $client->getVerification('health-check-probe');

            $this->info('Connection successful.');

            return self::SUCCESS;
        } catch (VerifierConnectionException $e) {
            $this->error('Connection failed: '.$e->getMessage());
            $this->newLine();
            $this->line('Troubleshooting tips:');
            $this->line('  - Make sure the swiyu verifier is running (e.g. via Docker)');
            $this->line('  - Check SWISS_EID_VERIFIER_URL in your .env file');
            $this->line('  - Verify there is no firewall blocking the connection');

            return self::FAILURE;
        } catch (SwissEidException $e) {
            // A 4xx response means the verifier is reachable but returned an API error
            // (e.g. "verification not found") — connection itself is working
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '400')) {
                $this->info('Connection successful (verifier is reachable).');

                return self::SUCCESS;
            }

            $this->error('Verifier returned an error: '.$e->getMessage());
            $this->newLine();
            $this->line('Troubleshooting tips:');
            $this->line('  - Check your SWISS_EID_AUTH_ENABLED, SWISS_EID_CLIENT_ID, and SWISS_EID_CLIENT_SECRET settings');
            $this->line('  - Ensure the management path is correct (SWISS_EID_VERIFIER_URL)');

            return self::FAILURE;
        }
    }
}
