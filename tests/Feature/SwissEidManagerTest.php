<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use SchaeferSoft\SwissEid\DTOs\PendingVerification;
use SchaeferSoft\SwissEid\DTOs\VerificationResult;
use SchaeferSoft\SwissEid\Enums\CredentialField;
use SchaeferSoft\SwissEid\Enums\VerificationState;
use SchaeferSoft\SwissEid\Exceptions\SwissEidException;
use SchaeferSoft\SwissEid\Exceptions\VerificationNotFoundException;
use SchaeferSoft\SwissEid\Facades\SwissEid;
use SchaeferSoft\SwissEid\Models\EidVerification;
use SchaeferSoft\SwissEid\SwissEidFake;
use SchaeferSoft\SwissEid\VerificationRequest;

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
        'credential_type' => 'test-sdjwt',
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
})->throws(AssertionFailedError::class);

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
            CredentialField::GivenName,
            'family_name',
        ])
        ->create();

    $record = EidVerification::where('verifier_id', 'remote-fields')->first();
    expect($record)->not->toBeNull();
});

it('sends direct_post.jwt response mode when configured', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-jwt-mode',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/jwt',
        ], 200),
    ]);

    SwissEid::verify()
        ->responseMode('direct_post.jwt')
        ->ageOver18()
        ->create();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['response_mode'] === 'direct_post.jwt';
    });
});

it('sends the verification purpose with strings wrapped as default localization', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-purpose',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/purpose',
        ], 200),
    ]);

    SwissEid::verify()
        ->ageOver18()
        ->purpose('com.example.age_check', 'Age verification', 'Required for checkout')
        ->create();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['verification_purpose'] ?? null) === [
            'scope' => 'com.example.age_check',
            'purpose_name' => ['default' => 'Age verification'],
            'purpose_description' => ['default' => 'Required for checkout'],
        ];
    });
});

it('throws when fetching an unknown verification id', function (): void {
    SwissEid::getVerification(verifierIdOrModelId: 'totally-missing');
})->throws(VerificationNotFoundException::class);

it('uses the per-request credential type when none is configured', function (): void {
    config()->set('swiss-eid.credentials.type', null);

    Http::fake([
        'localhost:8083/*' => Http::response([
            'id' => 'remote-override-type',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/override',
        ], 200),
    ]);

    SwissEid::verify()
        ->ageOver18()
        ->credentialType(['betaid-sdjwt', 'urn:vct:ch.admin.bcs.betaid'])
        ->create();

    $this->assertDatabaseHas(config('swiss-eid.table_name'), [
        'verifier_id' => 'remote-override-type',
        'credential_type' => 'betaid-sdjwt,urn:vct:ch.admin.bcs.betaid',
    ]);
});

it('throws when no credential type is configured or overridden', function (): void {
    config()->set('swiss-eid.credentials.type', null);

    SwissEid::verify()->ageOver18()->create();
})->throws(SwissEidException::class);

it('returns a new request for every verify call', function (): void {
    expect(SwissEid::verify())->toBeInstanceOf(VerificationRequest::class)
        ->not->toBe(SwissEid::verify());
});

it('does not share state between requests', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::sequence()
            ->push(['id' => 'remote-first', 'deeplink' => 'openid-vc://start'], 200)
            ->push(['id' => 'remote-second', 'deeplink' => 'openid-vc://start'], 200),
    ]);

    $abandoned = SwissEid::ageOver18()->forUser(7)->metadata(['order' => 1]);
    SwissEid::field('given_name')->create();
    $abandoned->create();

    $first = EidVerification::where('verifier_id', 'remote-first')->firstOrFail();
    $second = EidVerification::where('verifier_id', 'remote-second')->firstOrFail();

    expect($first->user_id)->toBeNull();
    expect($first->metadata)->toBeNull();
    expect($first->requested_fields)->toBe([['path' => ['given_name']]]);

    expect($second->user_id)->toBe(7);
    expect($second->metadata)->toBe(['order' => 1]);
    expect($second->requested_fields)->toBe([['path' => ['age_over_18']]]);
});
