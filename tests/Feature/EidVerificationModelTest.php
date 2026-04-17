<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Models\EidVerification;

it('creates a model with uuid primary key', function (): void {
    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'v-001',
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => ['$.age_over_18'],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect($model->id)->toBeString();
    expect(strlen($model->id))->toBe(36); // UUID length
});

it('casts state to VerificationState enum', function (): void {
    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'v-002',
        'state' => VerificationState::Success,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect($model->fresh()->state)->toBe(VerificationState::Success);
});

it('returns true for isPending() when state is pending', function (): void {
    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'v-003',
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect($model->isPending())->toBeTrue();
    expect($model->isSuccessful())->toBeFalse();
});

it('returns true for isExpired() when expires_at is in the past', function (): void {
    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'v-004',
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->subMinutes(1),
    ]);

    expect($model->isExpired())->toBeTrue();
});

it('scopePending filters only pending records', function (): void {
    EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'scope-pending',
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'scope-success',
        'state' => VerificationState::Success,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    $pendingCount = EidVerification::pending()->count();
    expect($pendingCount)->toBe(1);
});

it('scopeForUser filters by user id', function (): void {
    EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'user-scope-01',
        'user_id' => 7,
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'user-scope-02',
        'user_id' => 99,
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect(EidVerification::forUser(userId: 7)->count())->toBe(1);
    expect(EidVerification::forUser(userId: 99)->count())->toBe(1);
});

it('wraps model in a VerificationResult via toResult()', function (): void {
    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'result-wrap',
        'state' => VerificationState::Success,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    $result = $model->toResult();
    expect($result)->toBeInstanceOf(VerificationResult::class);
    expect($result->state)->toBe(VerificationState::Success);
});
