<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'swiss-eid:install';

    protected $description = 'Install the Laravel Swiss eID package (publishes config, migration and shows setup instructions)';

    public function handle(): int
    {
        $this->info('Installing Laravel Swiss eID...');
        $this->newLine();

        // 1. Publish config
        $this->call('vendor:publish', [
            '--tag' => 'swiss-eid-config',
            '--force' => false,
        ]);

        // 2. Publish migration
        $this->call('vendor:publish', [
            '--tag' => 'swiss-eid-migrations',
            '--force' => false,
        ]);

        // 3. Show required .env variables
        $this->newLine();
        $this->info('Add the following variables to your .env file:');
        $this->newLine();

        $envVars = <<<'ENV'
# swiyu Verifier
SWISS_EID_VERIFIER_URL=http://localhost:8083
SWISS_EID_TIMEOUT=10

# Webhook
SWISS_EID_WEBHOOK_PATH=/swiss-eid/webhook
SWISS_EID_WEBHOOK_KEY_HEADER=X-Verifier-Api-Key
SWISS_EID_WEBHOOK_API_KEY=your-secret-key-here

# Response mode ('direct_post' or 'direct_post.jwt' for encrypted wallet responses)
SWISS_EID_RESPONSE_MODE=direct_post

# Credentials
SWISS_EID_CREDENTIAL_TYPE=
SWISS_EID_ACCEPTED_ISSUERS=

# OAuth2 (optional, only if your verifier requires it)
SWISS_EID_AUTH_ENABLED=false
# SWISS_EID_TOKEN_URL=
# SWISS_EID_CLIENT_ID=
# SWISS_EID_CLIENT_SECRET=

# Polling
SWISS_EID_POLLING_ENABLED=true

# Verification TTL in seconds
SWISS_EID_VERIFICATION_TTL=300

# Database table name (optional, defaults to eid_verifications)
# SWISS_EID_TABLE_NAME=eid_verifications
ENV;

        $this->line($envVars);
        $this->newLine();

        // 4. Optionally run the migration
        if ($this->confirm('Would you like to run the migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('Laravel Swiss eID installed successfully.');
        $this->line('Documentation: https://github.com/schaefersoft/laravel-swiss-eid');

        return self::SUCCESS;
    }
}
