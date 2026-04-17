<?php

declare(strict_types=1);

use Carbon\Carbon;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Models\EidVerification;

function makeResult(VerificationState $state, ?array $data = null): VerificationResult
{
    $model = new EidVerification([
        'id' => 'test-uuid',
        'verifier_id' => 'verifier-uuid',
        'state' => $state,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    return new VerificationResult(
        id: 'test-uuid',
        state: $state,
        credentialData: $data,
        model: $model,
    );
}

it('reports isSuccessful() correctly', function (): void {
    expect(makeResult(VerificationState::Success)->isSuccessful())->toBeTrue();
    expect(makeResult(VerificationState::Failed)->isSuccessful())->toBeFalse();
    expect(makeResult(VerificationState::Pending)->isSuccessful())->toBeFalse();
});

it('reports isFailed() correctly', function (): void {
    expect(makeResult(VerificationState::Failed)->isFailed())->toBeTrue();
    expect(makeResult(VerificationState::Expired)->isFailed())->toBeTrue();
    expect(makeResult(VerificationState::Success)->isFailed())->toBeFalse();
});

it('reports isPending() correctly', function (): void {
    expect(makeResult(VerificationState::Pending)->isPending())->toBeTrue();
    expect(makeResult(VerificationState::Success)->isPending())->toBeFalse();
});

it('retrieves credential data fields via get()', function (): void {
    $result = makeResult(VerificationState::Success, ['given_name' => 'Anna', 'age_over_18' => true]);

    expect($result->get('given_name'))->toBe('Anna');
    expect($result->get('missing', 'fallback'))->toBe('fallback');
});

it('checks has() correctly', function (): void {
    $result = makeResult(VerificationState::Success, ['given_name' => 'Anna']);

    expect($result->has('given_name'))->toBeTrue();
    expect($result->has('family_name'))->toBeFalse();
});

it('checks isAdult() with boolean true', function (): void {
    $result = makeResult(VerificationState::Success, ['age_over_18' => true]);
    expect($result->isAdult())->toBeTrue();
});

it('checks isAdult() with string "true"', function (): void {
    $result = makeResult(VerificationState::Success, ['age_over_18' => 'true']);
    expect($result->isAdult())->toBeTrue();
});

it('checks isAdult() returns false when not set', function (): void {
    $result = makeResult(VerificationState::Success, []);
    expect($result->isAdult())->toBeFalse();
});

it('serialises to array', function (): void {
    $result = makeResult(VerificationState::Success, ['given_name' => 'Anna']);
    $arr = $result->toArray();

    expect($arr)->toHaveKeys(['id', 'state', 'is_successful', 'credential_data']);
    expect($arr['state'])->toBe('success');
    expect($arr['is_successful'])->toBeTrue();
});
