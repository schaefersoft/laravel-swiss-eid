<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'swiss-eid.verifier.base_url' => 'http://localhost:8083',
        'swiss-eid.verifier.timeout' => 10,
        'swiss-eid.verifier.response_mode' => 'direct_post',
        'swiss-eid.verifier.private_key' => null,
        'swiss-eid.webhook.path' => '/swiss-eid/webhook',
        'swiss-eid.webhook.api_key_header' => 'X-Verifier-Api-Key',
        'swiss-eid.webhook.api_key' => str_repeat('a', 32),
        'swiss-eid.credentials.type' => 'test-vct',
        'swiss-eid.credentials.accepted_issuers' => ['did:tdw:QmTest:example.com'],
        'swiss-eid.auth.enabled' => false,
        'swiss-eid.verification_ttl' => 300,
        'swiss-eid.user_id_type' => 'int',
        'app.url' => 'http://localhost',
    ]);
});

it('passes and exits 0 with a valid configuration', function (): void {
    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('All checks passed')
        ->assertExitCode(0);
});

// ── Verifier ─────────────────────────────────────────────────────────────────

it('fails when verifier URL is empty', function (): void {
    config(['swiss-eid.verifier.base_url' => '']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_VERIFIER_URL is not set')
        ->assertExitCode(1);
});

it('fails when verifier URL is not a valid URL', function (): void {
    config(['swiss-eid.verifier.base_url' => 'not-a-url']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_VERIFIER_URL is not a valid URL')
        ->assertExitCode(1);
});

it('fails when response mode is invalid', function (): void {
    config(['swiss-eid.verifier.response_mode' => 'unsupported_mode']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_RESPONSE_MODE must be one of')
        ->assertExitCode(1);
});

// ── Webhook ───────────────────────────────────────────────────────────────────

it('fails when webhook api key is not set', function (): void {
    config(['swiss-eid.webhook.api_key' => null]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_WEBHOOK_API_KEY is not set')
        ->assertExitCode(1);
});

it('warns when webhook api key is shorter than 32 characters', function (): void {
    config(['swiss-eid.webhook.api_key' => 'tooshort']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('shorter than 32 characters')
        ->assertExitCode(0);
});

// ── Credentials ───────────────────────────────────────────────────────────────

it('fails when credential type is not set', function (): void {
    config(['swiss-eid.credentials.type' => null]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_CREDENTIAL_TYPE is not set')
        ->assertExitCode(1);
});

it('fails when accepted issuers list is empty', function (): void {
    config(['swiss-eid.credentials.accepted_issuers' => []]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_ACCEPTED_ISSUERS is not set')
        ->assertExitCode(1);
});

// ── OAuth2 ────────────────────────────────────────────────────────────────────

it('validates oauth2 sub-config when auth is enabled', function (): void {
    config([
        'swiss-eid.auth.enabled' => true,
        'swiss-eid.auth.token_url' => null,
        'swiss-eid.auth.client_id' => null,
        'swiss-eid.auth.client_secret' => null,
    ]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_TOKEN_URL is not set')
        ->expectsOutputToContain('SWISS_EID_CLIENT_ID is not set')
        ->expectsOutputToContain('SWISS_EID_CLIENT_SECRET is not set')
        ->assertExitCode(1);
});

it('fails when token url is set but not a valid url', function (): void {
    config([
        'swiss-eid.auth.enabled' => true,
        'swiss-eid.auth.token_url' => 'not-a-url',
        'swiss-eid.auth.client_id' => 'client',
        'swiss-eid.auth.client_secret' => 'secret',
    ]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_TOKEN_URL is not a valid URL')
        ->assertExitCode(1);
});

// ── General ───────────────────────────────────────────────────────────────────

it('warns when verification TTL is very short', function (): void {
    config(['swiss-eid.verification_ttl' => 30]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('very short')
        ->assertExitCode(0);
});

it('fails when user_id_type is invalid', function (): void {
    config(['swiss-eid.user_id_type' => 'bigint']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_USER_ID_TYPE must be one of')
        ->assertExitCode(1);
});

// ── Private key ───────────────────────────────────────────────────────────────

it('fails when response mode is direct_post.jwt but no private key is set', function (): void {
    config([
        'swiss-eid.verifier.response_mode' => 'direct_post.jwt',
        'swiss-eid.verifier.private_key' => null,
    ]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('SWISS_EID_PRIVATE_KEY is not set')
        ->assertExitCode(1);
});

it('fails when private key is set but cannot be parsed', function (): void {
    config(['swiss-eid.verifier.private_key' => 'not-a-valid-pem-key']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('could not be parsed as a valid PEM private key')
        ->assertExitCode(1);
});

// ── DIDs ──────────────────────────────────────────────────────────────────────

it('fails when an accepted issuer DID is in an invalid format', function (): void {
    config(['swiss-eid.credentials.accepted_issuers' => ['not-a-did', 'also:wrong']]);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('Invalid DID format')
        ->assertExitCode(1);
});

// ── Webhook reachability ──────────────────────────────────────────────────────

it('skips reachability check when app url is localhost', function (): void {
    config(['app.url' => 'http://localhost']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('localhost — skipping')
        ->assertExitCode(0);
});

it('reports webhook reachable when endpoint returns 401', function (): void {
    Http::fake(['*' => Http::response(null, 401)]);
    config(['app.url' => 'https://example.com']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('Webhook reachable')
        ->assertExitCode(0);
});

it('reports webhook reachable when endpoint returns 403', function (): void {
    Http::fake(['*' => Http::response(null, 403)]);
    config(['app.url' => 'https://example.com']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('Webhook reachable')
        ->assertExitCode(0);
});

it('fails when webhook endpoint returns 404', function (): void {
    Http::fake(['*' => Http::response(null, 404)]);
    config(['app.url' => 'https://example.com']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('Webhook returned 404')
        ->assertExitCode(1);
});

it('warns when webhook returns 200 without an api key', function (): void {
    Http::fake(['*' => Http::response(null, 200)]);
    config(['app.url' => 'https://example.com']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('returned 200 without an API key')
        ->assertExitCode(0);
});

it('fails when webhook is not reachable', function (): void {
    Http::fake(['*' => fn () => throw new ConnectionException('refused')]);
    config(['app.url' => 'https://example.com']);

    $this->artisan('swiss-eid:doctor')
        ->expectsOutputToContain('not reachable')
        ->assertExitCode(1);
});
