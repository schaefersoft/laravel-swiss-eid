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
        'credential_type' => 'test-sdjwt',
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

it('retrieves nested credential data via dot-notation', function (): void {
    $result = makeResult(VerificationState::Success, [
        'address' => ['street_address' => 'Bahnhofstrasse 1', 'locality' => 'Zürich'],
    ]);

    expect($result->get('address.street_address'))->toBe('Bahnhofstrasse 1')
        ->and($result->get('address.country', 'CH'))->toBe('CH')
        ->and($result->has('address.locality'))->toBeTrue()
        ->and($result->has('address.country'))->toBeFalse();
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

it('exposes the verifier error code and detects user rejection', function (): void {
    $model = new EidVerification([
        'id' => 'test-uuid',
        'verifier_id' => 'verifier-uuid',
        'state' => VerificationState::Failed,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    $result = new VerificationResult(
        id: 'test-uuid',
        state: VerificationState::Failed,
        credentialData: null,
        model: $model,
        errorCode: 'client_rejected',
        errorDescription: 'The holder rejected the verification request.',
    );

    expect($result->errorCode)->toBe('client_rejected')
        ->and($result->wasRejectedByUser())->toBeTrue()
        ->and($result->toArray()['error_code'])->toBe('client_rejected');
});

it('reports wasRejectedByUser() as false for technical failures', function (): void {
    expect(makeResult(VerificationState::Failed)->wasRejectedByUser())->toBeFalse();
});
