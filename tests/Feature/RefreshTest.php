<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SchaeferSoft\SwissEid\Enums\VerificationState;
use SchaeferSoft\SwissEid\Events\VerificationCompleted;
use SchaeferSoft\SwissEid\Events\VerificationExpired;
use SchaeferSoft\SwissEid\Events\VerificationFailed;
use SchaeferSoft\SwissEid\Exceptions\VerificationNotFoundException;
use SchaeferSoft\SwissEid\Facades\SwissEid;
use SchaeferSoft\SwissEid\Models\EidVerification;

function createRefreshRecord(string $verifierId, VerificationState $state = VerificationState::Pending): EidVerification
{
    return EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => $verifierId,
        'state' => $state,
        'credential_type' => 'test-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);
}

it('pulls a successful result from the verifier', function (): void {
    Event::fake([VerificationCompleted::class]);

    $verification = createRefreshRecord('refresh-success');

    Http::fake([
        'localhost:8083/management/api/verifications/refresh-success' => Http::response([
            'state' => 'SUCCESS',
            'wallet_response' => ['credential_subject_data' => ['age_over_18' => true]],
        ], 200),
    ]);

    $result = SwissEid::refresh($verification->id);

    expect($result->state)->toBe(VerificationState::Success)
        ->and($result->isAdult())->toBeTrue()
        ->and($verification->fresh()->webhook_received_at)->toBeNull();

    Event::assertDispatched(VerificationCompleted::class);
});

it('accepts the verifier id', function (): void {
    Event::fake([VerificationFailed::class]);

    createRefreshRecord('refresh-failed');

    Http::fake([
        'localhost:8083/management/api/verifications/refresh-failed' => Http::response(['state' => 'FAILED'], 200),
    ]);

    expect(SwissEid::refresh('refresh-failed')->state)->toBe(VerificationState::Failed);

    Event::assertDispatched(VerificationFailed::class);
});

it('keeps the verification pending while the verifier is pending', function (): void {
    Event::fake([VerificationCompleted::class, VerificationFailed::class, VerificationExpired::class]);

    $verification = createRefreshRecord('refresh-pending');

    Http::fake([
        'localhost:8083/management/api/verifications/refresh-pending' => Http::response(['state' => 'PENDING'], 200),
    ]);

    expect(SwissEid::refresh($verification->id)->state)->toBe(VerificationState::Pending);

    Event::assertNotDispatched(VerificationCompleted::class);
    Event::assertNotDispatched(VerificationFailed::class);
    Event::assertNotDispatched(VerificationExpired::class);
});

it('marks the verification expired when the verifier returns 404', function (): void {
    Event::fake([VerificationExpired::class]);

    $verification = createRefreshRecord('refresh-expired');

    Http::fake([
        'localhost:8083/management/api/verifications/refresh-expired' => Http::response([], 404),
    ]);

    expect(SwissEid::refresh($verification->id)->state)->toBe(VerificationState::Expired);

    Event::assertDispatched(VerificationExpired::class);
});

it('does not contact the verifier for terminal verifications', function (): void {
    Http::fake();

    $verification = createRefreshRecord('refresh-terminal', VerificationState::Success);

    expect(SwissEid::refresh($verification->id)->state)->toBe(VerificationState::Success);

    Http::assertNothingSent();
});

it('throws when refreshing an unknown verification', function (): void {
    SwissEid::refresh('missing');
})->throws(VerificationNotFoundException::class);

it('refreshes all pending verifications via artisan', function (): void {
    Event::fake();

    $success = createRefreshRecord('cmd-success');
    $pending = createRefreshRecord('cmd-pending');
    createRefreshRecord('cmd-terminal', VerificationState::Failed);

    Http::fake([
        'localhost:8083/management/api/verifications/cmd-success' => Http::response(['state' => 'SUCCESS'], 200),
        'localhost:8083/management/api/verifications/cmd-pending' => Http::response(['state' => 'PENDING'], 200),
    ]);

    $this->artisan('swiss-eid:refresh')
        ->expectsOutputToContain('Refreshed 2 verification(s).')
        ->assertExitCode(0);

    expect($success->fresh()->state)->toBe(VerificationState::Success)
        ->and($pending->fresh()->state)->toBe(VerificationState::Pending);

    Http::assertSentCount(2);
});

it('refreshes a single verification via artisan and reports errors', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([], 500),
    ]);

    createRefreshRecord('cmd-error');

    $this->artisan('swiss-eid:refresh', ['id' => 'cmd-error'])
        ->expectsOutputToContain('Refreshed 0 verification(s).')
        ->assertExitCode(1);
});

it('supports refresh on the fake', function (): void {
    $fake = SwissEid::fake();

    expect(SwissEid::refresh('anything')->isSuccessful())->toBeTrue();

    $fake->assertVerificationCompleted();
});
