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

it('returns 404 if verification not found', function (): void {
    Http::fake([
        '*' => Http::response(['state' => 'SUCCESS'], 200),
    ]);

    $this->postJson('/swiss-eid/webhook', [
        'verification_id' => 'does-not-exist',
    ], ['X-Verifier-Api-Key' => 'test-secret'])
        ->assertStatus(404);
});
