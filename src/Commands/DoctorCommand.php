<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DoctorCommand extends Command
{
    protected $signature = 'swiss-eid:doctor';

    protected $description = 'Validate the Swiss eID configuration, private key, DIDs, and webhook reachability';

    /** Minimum swiyu-verifier version: CD-004 wallet enforcement + CD-008 trust-validation fix. */
    private const MIN_VERIFIER_VERSION = '4.1.2';

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=blue;options=bold>Swiss eID Doctor</> — configuration diagnostics');
        $this->newLine();

        $this->runSection('Verifier', fn () => $this->checkVerifierConfig());
        $this->runSection('Verifier Version', fn () => $this->checkVerifierVersion());
        $this->runSection('Webhook', fn () => $this->checkWebhookConfig());
        $this->runSection('Credentials', fn () => $this->checkCredentialConfig());
        $this->runSection('OAuth2 Auth', fn () => $this->checkAuthConfig());
        $this->runSection('General', fn () => $this->checkGeneralConfig());
        $this->runSection('DID Formats (accepted_issuers)', fn () => $this->checkDidFormats());
        $this->runSection('Webhook Reachability', fn () => $this->checkWebhookReachability());

        $this->newLine();
        $this->printSummary();

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkVerifierConfig(): void
    {
        $url = config('swiss-eid.verifier.base_url');
        if (empty($url)) {
            $this->checkFail('SWISS_EID_VERIFIER_URL is not set');
        } elseif (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->checkFail("SWISS_EID_VERIFIER_URL is not a valid URL: {$url}");
        } else {
            $this->checkOk("Verifier URL: {$url}");
        }

        $timeout = config('swiss-eid.verifier.timeout');
        if (! is_numeric($timeout) || (int) $timeout <= 0) {
            $this->checkFail("SWISS_EID_TIMEOUT must be a positive integer, got: {$timeout}");
        } else {
            $this->checkOk("Timeout: {$timeout}s");
        }

        $mode = config('swiss-eid.verifier.response_mode');
        $validModes = ['direct_post', 'direct_post.jwt'];
        if (! in_array($mode, $validModes, true)) {
            $this->checkFail('SWISS_EID_RESPONSE_MODE must be one of ['.implode(', ', $validModes)."], got: {$mode}");
        } elseif ($mode === 'direct_post') {
            $this->checkWarn('SWISS_EID_RESPONSE_MODE is direct_post — swiyu wallets enforce encrypted responses (direct_post.jwt) since 2026-08-17 (CD-004)');
        } else {
            $this->checkOk("Response mode: {$mode}");
        }
    }

    private function checkVerifierVersion(): void
    {
        $baseUrl = rtrim((string) config('swiss-eid.verifier.base_url'), '/');

        try {
            $info = Http::timeout(5)->acceptJson()->get($baseUrl.'/actuator/info')->throw()->json();
        } catch (\Throwable) {
            $this->line('    - Could not read /actuator/info — manually ensure swiyu-verifier >= '.self::MIN_VERIFIER_VERSION);

            return;
        }

        $version = $info['build']['version'] ?? $info['app']['version'] ?? null;

        if (! is_string($version) || $version === '') {
            $this->line('    - Verifier exposes no version info — manually ensure swiyu-verifier >= '.self::MIN_VERIFIER_VERSION);

            return;
        }

        if (version_compare($version, self::MIN_VERIFIER_VERSION, '<')) {
            $this->checkFail("swiyu-verifier {$version} is outdated — upgrade to >= ".self::MIN_VERIFIER_VERSION.' (CD-004 wallet enforcement, CD-008 critical trust-validation fix)');
        } else {
            $this->checkOk("swiyu-verifier version: {$version}");
        }
    }

    private function checkWebhookConfig(): void
    {
        $path = config('swiss-eid.webhook.path');
        if (empty($path)) {
            $this->checkFail('SWISS_EID_WEBHOOK_PATH is not set');
        } elseif (! str_starts_with((string) $path, '/')) {
            $this->checkWarn("SWISS_EID_WEBHOOK_PATH should start with /: {$path}");
        } else {
            $this->checkOk("Webhook path: {$path}");
        }

        $header = config('swiss-eid.webhook.api_key_header');
        if (empty($header)) {
            $this->checkFail('SWISS_EID_WEBHOOK_KEY_HEADER is not set');
        } else {
            $this->checkOk("API key header: {$header}");
        }

        $apiKey = config('swiss-eid.webhook.api_key');
        if (empty($apiKey)) {
            $this->checkFail('SWISS_EID_WEBHOOK_API_KEY is not set — the webhook endpoint is unprotected');
        } elseif (strlen((string) $apiKey) < 32) {
            $this->checkWarn('SWISS_EID_WEBHOOK_API_KEY is shorter than 32 characters — consider a stronger secret');
        } else {
            $this->checkOk('Webhook API key: set ('.strlen((string) $apiKey).' chars)');
        }
    }

    private function checkCredentialConfig(): void
    {
        $type = config('swiss-eid.credentials.type');
        if (empty($type)) {
            $this->checkFail('SWISS_EID_CREDENTIAL_TYPE is not set');
        } else {
            $this->checkOk("Credential type (vct): {$type}");
        }

        $issuers = config('swiss-eid.credentials.accepted_issuers', []);
        $anchors = config('swiss-eid.credentials.trust_anchors', []);

        if (empty($issuers) && empty($anchors)) {
            $this->checkFail('SWISS_EID_ACCEPTED_ISSUERS is not set and no trust_anchors configured — the verifier rejects requests without at least one');

            return;
        }

        if (! empty($issuers)) {
            $this->checkOk(count($issuers).' accepted issuer(s) configured');
        }

        foreach ((array) $anchors as $anchor) {
            $did = (string) ($anchor['did'] ?? '');
            $uri = (string) ($anchor['trust_registry_uri'] ?? '');

            if ($did === '' || ! str_starts_with($uri, 'https://')) {
                $this->checkFail('Each trust anchor needs a did and an https trust_registry_uri');
            } else {
                $this->checkOk("Trust anchor: {$did}");
            }
        }
    }

    private function checkAuthConfig(): void
    {
        $enabled = config('swiss-eid.auth.enabled', false);

        if (! $enabled) {
            $this->checkOk('OAuth2 disabled (SWISS_EID_AUTH_ENABLED=false)');

            return;
        }

        $this->checkOk('OAuth2 enabled');

        $tokenUrl = config('swiss-eid.auth.token_url');
        if (empty($tokenUrl)) {
            $this->checkFail('SWISS_EID_TOKEN_URL is not set (required when auth is enabled)');
        } elseif (filter_var($tokenUrl, FILTER_VALIDATE_URL) === false) {
            $this->checkFail("SWISS_EID_TOKEN_URL is not a valid URL: {$tokenUrl}");
        } else {
            $this->checkOk("Token URL: {$tokenUrl}");
        }

        if (empty(config('swiss-eid.auth.client_id'))) {
            $this->checkFail('SWISS_EID_CLIENT_ID is not set (required when auth is enabled)');
        } else {
            $this->checkOk('Client ID: set');
        }

        if (empty(config('swiss-eid.auth.client_secret'))) {
            $this->checkFail('SWISS_EID_CLIENT_SECRET is not set (required when auth is enabled)');
        } else {
            $this->checkOk('Client secret: set');
        }
    }

    private function checkGeneralConfig(): void
    {
        $ttl = config('swiss-eid.verification_ttl');
        if (! is_numeric($ttl) || (int) $ttl <= 0) {
            $this->checkFail("SWISS_EID_VERIFICATION_TTL must be a positive integer, got: {$ttl}");
        } elseif ((int) $ttl < 60) {
            $this->checkWarn("SWISS_EID_VERIFICATION_TTL is very short ({$ttl}s) — users may not finish in time");
        } else {
            $this->checkOk("Verification TTL: {$ttl}s");
        }

        $userIdType = config('swiss-eid.user_id_type');
        $validTypes = ['int', 'uuid', 'string'];
        if (! in_array($userIdType, $validTypes, true)) {
            $this->checkFail('SWISS_EID_USER_ID_TYPE must be one of ['.implode(', ', $validTypes)."], got: {$userIdType}");
        } else {
            $this->checkOk("User ID column type: {$userIdType}");
        }
    }

    private function checkDidFormats(): void
    {
        /** @var string[] $issuers */
        $issuers = config('swiss-eid.credentials.accepted_issuers', []);

        if (empty($issuers)) {
            $this->checkWarn('No accepted issuers configured — skipping DID validation');

            return;
        }

        foreach ($issuers as $did) {
            if (! preg_match('/^did:[a-z][a-z0-9]*:.+$/', $did)) {
                $this->checkFail("Invalid DID format: {$did}");
            } else {
                [, $method] = explode(':', $did, 3);
                $preview = mb_strlen($did) > 60 ? mb_substr($did, 0, 57).'…' : $did;

                if ($method === 'tdw') {
                    $this->checkWarn("did:tdw is deprecated — re-onboard to did:webvh (updates to did:tdw DIDs are frozen from ~Oct 2026) — {$preview}");
                } else {
                    $this->checkOk("Valid DID (method: did:{$method}:…) — {$preview}");
                }
            }
        }
    }

    private function checkWebhookReachability(): void
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $webhookPath = (string) config('swiss-eid.webhook.path', '/swiss-eid/webhook');

        if (empty($appUrl) || in_array($appUrl, ['http://localhost', 'https://localhost'], true)) {
            $this->checkOk('APP_URL is localhost — skipping external reachability check');

            return;
        }

        $webhookUrl = $appUrl.$webhookPath;
        $this->line("      Probing: {$webhookUrl}");

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->post($webhookUrl);

            $status = $response->status();

            // A 401/403 from the key middleware proves the route is live and protected — ideal.
            // 405 means the route exists but rejects the method — still reachable.
            if (in_array($status, [401, 403], true)) {
                $this->checkOk("Webhook reachable — HTTP {$status} (auth middleware is rejecting unauthenticated requests correctly)");
            } elseif ($status === 405) {
                $this->checkOk('Webhook reachable — HTTP 405 (route exists, method check active)');
            } elseif ($status === 200) {
                $this->checkWarn('Webhook returned 200 without an API key — verify VerifyWebhookApiKey middleware is applied');
            } elseif ($status === 404) {
                $this->checkFail('Webhook returned 404 — route not registered or APP_URL does not match the running app');
            } else {
                $this->checkWarn("Webhook returned HTTP {$status} — verify the endpoint is correctly configured");
            }
        } catch (ConnectionException $e) {
            $this->checkFail("Webhook is not reachable from this host: {$e->getMessage()}");
        } catch (\Throwable $e) {
            $this->checkFail("Webhook reachability check failed: {$e->getMessage()}");
        }
    }

    private function printSummary(): void
    {
        if ($this->errors === 0 && $this->warnings === 0) {
            $this->components->info('All checks passed — your Swiss eID configuration looks good.');
        } elseif ($this->errors === 0) {
            $this->components->warn("{$this->warnings} warning(s) — review before going to production.");
        } else {
            $this->components->error("{$this->errors} error(s), {$this->warnings} warning(s) — fix the errors before using Swiss eID.");
        }
    }

    private function runSection(string $title, \Closure $checks): void
    {
        $this->line("  <options=bold>{$title}</>");
        $checks();
        $this->newLine();
    }

    private function checkOk(string $message): void
    {
        $this->line("    <fg=green>✓</> {$message}");
    }

    private function checkWarn(string $message): void
    {
        $this->line("    <fg=yellow>!</> {$message}");
        $this->warnings++;
    }

    private function checkFail(string $message): void
    {
        $this->line("    <fg=red>✗</> {$message}");
        $this->errors++;
    }
}
