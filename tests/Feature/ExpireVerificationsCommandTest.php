<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Events\VerificationExpired;
use SwissEid\LaravelSwissEid\Models\EidVerification;

it('marks expired pending verifications and dispatches VerificationExpired', function (): void {
    Event::fake([VerificationExpired::class]);

    $expired = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => Str::uuid()->toString(),
        'state' => VerificationState::Pending,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->subMinute(),
    ]);

    $active = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => Str::uuid()->toString(),
        'state' => VerificationState::Pending,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(10),
    ]);

    $this->artisan('swiss-eid:expire')
        ->expectsOutputToContain('Marked 1 verification(s) as expired.')
        ->assertExitCode(0);

    expect($expired->fresh()->state)->toBe(VerificationState::Expired)
        ->and($active->fresh()->state)->toBe(VerificationState::Pending);

    Event::assertDispatched(VerificationExpired::class, 1);
});

it('leaves already-terminal verifications untouched', function (): void {
    Event::fake([VerificationExpired::class]);

    $success = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => Str::uuid()->toString(),
        'state' => VerificationState::Success,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->subMinute(),
    ]);

    $this->artisan('swiss-eid:expire')
        ->expectsOutputToContain('Marked 0 verification(s) as expired.')
        ->assertExitCode(0);

    expect($success->fresh()->state)->toBe(VerificationState::Success);

    Event::assertNotDispatched(VerificationExpired::class);
});
