<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\DTOs\PendingVerification;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Facades\SwissEid;
use SwissEid\LaravelSwissEid\Models\EidVerification;
use SwissEid\LaravelSwissEid\SwissEidFake;

it('creates a pending verification via manager', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-verifier-id',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/remote-verifier-id',
        ], 200),
    ]);

    $pending = SwissEid::verify()
        ->ageOver18()
        ->create();

    expect($pending)->toBeInstanceOf(PendingVerification::class);
    expect($pending->verifierId)->toBe('remote-verifier-id');
    expect($pending->deeplink)->toBe('openid-vc://start');

    $this->assertDatabaseHas(config('swiss-eid.table_name'), [
        'verifier_id' => 'remote-verifier-id',
        'state' => 'pending',
    ]);
});

it('associates verification with a user', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-user-verify',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/1',
        ], 200),
    ]);

    SwissEid::verify()
        ->ageOver18()
        ->forUser(userId: 42)
        ->create();

    $this->assertDatabaseHas(config('swiss-eid.table_name'), [
        'verifier_id' => 'remote-user-verify',
        'user_id' => 42,
    ]);
});

it('stores metadata with the verification', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-meta',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/meta',
        ], 200),
    ]);

    SwissEid::verify()
        ->ageOver18()
        ->metadata(data: ['context' => 'checkout', 'order_id' => 99])
        ->create();

    $record = EidVerification::where('verifier_id', 'remote-meta')->first();
    expect($record->metadata['context'])->toBe('checkout');
    expect($record->metadata['order_id'])->toBe(99);
});

it('retrieves a verification result by local id', function (): void {
    $record = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'get-test-01',
        'state' => VerificationState::Success,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => now()->addMinutes(5),
    ]);

    $result = SwissEid::getVerification(verifierIdOrModelId: $record->id);

    expect($result)->toBeInstanceOf(VerificationResult::class);
    expect($result->isSuccessful())->toBeTrue();
});

it('can use the fake for testing', function (): void {
    $fake = SwissEid::fake();

    expect($fake)->toBeInstanceOf(SwissEidFake::class);

    $pending = SwissEid::verify()->ageOver18()->create();
    expect($pending)->toBeInstanceOf(PendingVerification::class);

    $fake->assertVerificationStarted();
});

it('fake assertNothingStarted passes when nothing was created', function (): void {
    $fake = SwissEid::fake();
    $fake->assertNothingStarted();
});

it('fake assertVerificationCompleted fires after getVerification', function (): void {
    $fake = SwissEid::fake();
    $result = SwissEidFake::fakeVerification(state: 'success', data: ['given_name' => 'Anna']);

    $fake = SwissEid::fake([
        $result->id => $result,
    ]);

    SwissEid::getVerification(verifierIdOrModelId: $result->id);
    $fake->assertVerificationCompleted();
});

it('fake getVerification returns a default success when no predefined match', function (): void {
    $fake = SwissEid::fake();

    $result = SwissEid::getVerification(verifierIdOrModelId: 'unknown-id');

    expect($result)->toBeInstanceOf(VerificationResult::class)
        ->and($result->isSuccessful())->toBeTrue();
});

it('fake assertVerificationCompleted accepts a matching callback', function (): void {
    $result = SwissEidFake::fakeVerification(state: 'success', data: ['given_name' => 'Anna']);
    $fake = SwissEid::fake([$result->id => $result]);

    SwissEid::getVerification(verifierIdOrModelId: $result->id);

    $fake->assertVerificationCompleted(
        fn (VerificationResult $r) => $r->get('given_name') === 'Anna',
    );
});

it('fake assertVerificationCompleted fails when callback never matches', function (): void {
    $result = SwissEidFake::fakeVerification(state: 'success', data: ['given_name' => 'Anna']);
    $fake = SwissEid::fake([$result->id => $result]);

    SwissEid::getVerification(verifierIdOrModelId: $result->id);

    $fake->assertVerificationCompleted(
        fn (VerificationResult $r) => $r->get('given_name') === 'Bob',
    );
})->throws(\PHPUnit\Framework\AssertionFailedError::class);

it('manager fields() accepts CredentialField enum values', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-fields',
            'deeplink' => 'openid-vc://fields',
            'verificationUrl' => 'http://localhost:8083/verify/fields',
        ], 200),
    ]);

    SwissEid::verify()
        ->fields([
            \SwissEid\LaravelSwissEid\Enums\CredentialField::GivenName,
            'family_name',
        ])
        ->create();

    $record = EidVerification::where('verifier_id', 'remote-fields')->first();
    expect($record)->not->toBeNull();
});

it('throws when fetching an unknown verification id', function (): void {
    SwissEid::getVerification(verifierIdOrModelId: 'totally-missing');
})->throws(\SwissEid\LaravelSwissEid\Exceptions\VerificationNotFoundException::class);
