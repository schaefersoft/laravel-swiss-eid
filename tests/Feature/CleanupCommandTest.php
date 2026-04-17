<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Models\EidVerification;

function makeVerification(Carbon $expiresAt, VerificationState $state = VerificationState::Pending): EidVerification
{
    return EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => Str::uuid()->toString(),
        'state' => $state,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => $expiresAt,
    ]);
}

it('deletes expired verifications older than the cutoff', function (): void {
    $old = makeVerification(Carbon::now()->subDays(10));
    $recent = makeVerification(Carbon::now()->subDays(1));
    $future = makeVerification(Carbon::now()->addDays(1));

    $this->artisan('swiss-eid:cleanup', ['--days' => 7])
        ->expectsOutputToContain('Deleted 1 expired verification record(s) older than 7 days.')
        ->assertExitCode(0);

    expect(EidVerification::find($old->id))->toBeNull();
    expect(EidVerification::find($recent->id))->not->toBeNull();
    expect(EidVerification::find($future->id))->not->toBeNull();
});

it('does not delete records when --dry-run is supplied', function (): void {
    $old = makeVerification(Carbon::now()->subDays(30));

    $this->artisan('swiss-eid:cleanup', ['--days' => 7, '--dry-run' => true])
        ->expectsOutputToContain('[Dry run] Would delete 1 expired verification record(s) older than 7 days.')
        ->assertExitCode(0);

    expect(EidVerification::find($old->id))->not->toBeNull();
});

it('uses the default 7-day cutoff when --days is omitted', function (): void {
    makeVerification(Carbon::now()->subDays(10));
    makeVerification(Carbon::now()->subDays(3));

    $this->artisan('swiss-eid:cleanup')
        ->expectsOutputToContain('Deleted 1 expired verification record(s) older than 7 days.')
        ->assertExitCode(0);
});
