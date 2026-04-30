<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Events\VerificationExpired;
use SwissEid\LaravelSwissEid\Models\EidVerification;

beforeEach(function (): void {
    config()->set('swiss-eid.polling.enabled', true);
    config()->set('swiss-eid.polling.route_path', '/swiss-eid/status');
});

it('returns pending state for a pending verification', function (): void {
    $verification = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'poll-test-01',
        'state' => VerificationState::Pending,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    $this->getJson('/swiss-eid/status/'.$verification->id)
        ->assertOk()
        ->assertJson([
            'state' => 'pending',
            'is_terminal' => false,
        ]);
});

it('returns success state for a completed verification', function (): void {
    $verification = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'poll-test-02',
        'state' => VerificationState::Success,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    $this->getJson('/swiss-eid/status/'.$verification->id)
        ->assertOk()
        ->assertJson([
            'state' => 'success',
            'is_terminal' => true,
        ]);
});

it('marks expired verifications and fires VerificationExpired event', function (): void {
    Event::fake([VerificationExpired::class]);

    $verification = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'poll-test-expired',
        'state' => VerificationState::Pending,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->subMinutes(1), // already expired
    ]);

    $this->getJson('/swiss-eid/status/'.$verification->id)
        ->assertOk()
        ->assertJson([
            'state' => 'expired',
            'is_terminal' => true,
        ]);

    Event::assertDispatched(VerificationExpired::class);
});

it('returns 404 for unknown verification id', function (): void {
    $this->getJson('/swiss-eid/status/non-existent-uuid')
        ->assertStatus(404);
});
