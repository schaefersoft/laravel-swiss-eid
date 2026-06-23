<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Events\VerificationCompleted;
use SwissEid\LaravelSwissEid\Events\VerificationFailed;
use SwissEid\LaravelSwissEid\Models\EidVerification;

beforeEach(function (): void {
    config()->set('swiss-eid.webhook.api_key', 'test-secret');
    config()->set('swiss-eid.webhook.api_key_header', 'X-Verifier-Api-Key');
    config()->set('swiss-eid.webhook.path', '/swiss-eid/webhook');
});

function createVerificationRecord(string $verifierId = 'verifier-abc'): EidVerification
{
    return EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => $verifierId,
        'state' => VerificationState::Pending,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);
}

it('rejects webhook requests without api key', function (): void {
    $this->postJson('/swiss-eid/webhook', ['verification_id' => 'test'])
        ->assertStatus(401);
});

it('rejects webhook requests with wrong api key', function (): void {
    $this->postJson('/swiss-eid/webhook', ['verification_id' => 'test'], [
        'X-Verifier-Api-Key' => 'wrong-key',
    ])->assertStatus(401);
});

it('processes a successful webhook', function (): void {
    Event::fake([VerificationCompleted::class]);

    $verification = createVerificationRecord('verifier-success-01');

    Http::fake([
        'localhost:8083/management/api/verifications/verifier-success-01' => Http::response([
            'state' => 'SUCCESS',
            'wallet_response' => [
                'credential_subject_data' => [
                    'given_name' => 'Anna',
                    'age_over_18' => true,
                ],
            ],
        ], 200),
    ]);

    $this->postJson('/swiss-eid/webhook', [
        'verification_id' => 'verifier-success-01',
    ], ['X-Verifier-Api-Key' => 'test-secret'])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $verification->refresh();
    expect($verification->state)->toBe(VerificationState::Success);
    expect($verification->credential_data['given_name'])->toBe('Anna');

    Event::assertDispatched(VerificationCompleted::class);
});

it('processes a failed webhook', function (): void {
    Event::fake([VerificationFailed::class]);

    $verification = createVerificationRecord('verifier-failed-01');

    Http::fake([
        'localhost:8083/management/api/verifications/verifier-failed-01' => Http::response([
            'state' => 'FAILED',
        ], 200),
    ]);

    $this->postJson('/swiss-eid/webhook', [
        'verification_id' => 'verifier-failed-01',
    ], ['X-Verifier-Api-Key' => 'test-secret'])
        ->assertOk();

    $verification->refresh();
    expect($verification->state)->toBe(VerificationState::Failed);

    Event::assertDispatched(VerificationFailed::class);
});

it('persists the error code and description from a failed verification', function (): void {
    Event::fake([VerificationFailed::class]);

    $verification = createVerificationRecord('verifier-rejected-01');

    Http::fake([
        'localhost:8083/management/api/verifications/verifier-rejected-01' => Http::response([
            'state' => 'FAILED',
            'wallet_response' => [
                'error_code' => 'client_rejected',
                'error_description' => 'The holder rejected the verification request.',
            ],
        ], 200),
    ]);

    $this->postJson('/swiss-eid/webhook', [
        'verification_id' => 'verifier-rejected-01',
    ], ['X-Verifier-Api-Key' => 'test-secret'])->assertOk();

    $verification->refresh();
    expect($verification->state)->toBe(VerificationState::Failed)
        ->and($verification->error_code)->toBe('client_rejected')
        ->and($verification->error_description)->toBe('The holder rejected the verification request.');

    expect($verification->toResult()->wasRejectedByUser())->toBeTrue();

    Event::assertDispatched(VerificationFailed::class);
});

it('acknowledges webhooks for unknown verification ids with 200 to stop retries', function (): void {
    Http::fake([
        '*' => Http::response(['state' => 'SUCCESS'], 200),
    ]);

    $this->postJson('/swiss-eid/webhook', [
        'verification_id' => 'does-not-exist',
    ], ['X-Verifier-Api-Key' => 'test-secret'])
        ->assertOk()
        ->assertJson(['status' => 'ignored']);
});

it('is idempotent and ignores webhooks for already-terminal verifications', function (): void {
    Event::fake([VerificationCompleted::class, VerificationFailed::class]);
    Http::fake();

    $verification = createVerificationRecord('verifier-terminal-01');
    $verification->update(['state' => VerificationState::Success]);

    $this->postJson('/swiss-eid/webhook', [
        'verification_id' => 'verifier-terminal-01',
    ], ['X-Verifier-Api-Key' => 'test-secret'])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Event::assertNotDispatched(VerificationCompleted::class);
    Event::assertNotDispatched(VerificationFailed::class);
    Http::assertNothingSent();
});
