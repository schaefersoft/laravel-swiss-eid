<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use SwissEid\LaravelSwissEid\DTOs\PendingVerification;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Models\EidVerification;

/**
 * Test double for SwissEidManager that records calls and returns pre-defined results.
 *
 * Usage:
 *   $fake = SwissEid::fake();
 *   // ... trigger code under test ...
 *   $fake->assertVerificationStarted();
 */
final class SwissEidFake extends SwissEidManager
{
    /** @var list<PendingVerification> */
    private array $createdVerifications = [];

    /** @var list<VerificationResult> */
    private array $completedVerifications = [];

    /** @var array<string, VerificationResult> */
    private array $predefinedResults = [];

    /**
     * @param  array<string, mixed>  $responses  Keyed by verifier_id => VerificationResult
     */
    public static function make(array $responses = []): static
    {
        $fake = new self(
            client: new VerifierClient([
                'verifier' => ['base_url' => 'http://fake', 'management_path' => '/management/api', 'timeout' => 10],
                'auth' => ['enabled' => false, 'token_url' => null, 'client_id' => null, 'client_secret' => null],
                'credentials' => ['type' => 'betaid-sdjwt', 'sd_jwt_alg' => 'ES256', 'kb_jwt_alg' => 'ES256', 'accepted_issuers' => []],
                'verification_ttl' => 300,
            ]),
            config: [
                'verifier' => ['base_url' => 'http://fake', 'management_path' => '/management/api', 'timeout' => 10],
                'auth' => ['enabled' => false, 'token_url' => null, 'client_id' => null, 'client_secret' => null],
                'credentials' => ['type' => 'betaid-sdjwt', 'sd_jwt_alg' => 'ES256', 'kb_jwt_alg' => 'ES256', 'accepted_issuers' => []],
                'polling' => ['enabled' => true, 'route_path' => '/swiss-eid/status'],
                'verification_ttl' => 300,
            ],
        );

        foreach ($responses as $verifierId => $result) {
            $fake->predefinedResults[$verifierId] = $result;
        }

        return $fake;
    }

    /**
     * Create a pre-built VerificationResult for use in assertions or fake responses.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fakeVerification(
        string $state = 'success',
        array $data = [],
    ): VerificationResult {
        $verificationState = VerificationState::from($state);
        $id = Str::uuid()->toString();

        $model = new EidVerification([
            'id' => $id,
            'verifier_id' => Str::uuid()->toString(),
            'state' => $verificationState,
            'credential_type' => 'betaid-sdjwt',
            'requested_fields' => [],
            'credential_data' => $data ?: null,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        return new VerificationResult(
            id: $id,
            state: $verificationState,
            credentialData: $data ?: null,
            model: $model,
        );
    }

    /**
     * Override create() to record the call and return a fake PendingVerification.
     */
    public function create(): PendingVerification
    {
        $id = Str::uuid()->toString();
        $verifierId = Str::uuid()->toString();

        $pending = new PendingVerification(
            id: $id,
            verifierId: $verifierId,
            deeplink: 'openid-vc://fake-deeplink/'.$id,
            verificationUrl: 'http://fake/verifications/'.$verifierId,
            state: 'pending',
            expiresAt: Carbon::now()->addMinutes(5),
        );

        $this->createdVerifications[] = $pending;

        return $pending;
    }

    /**
     * Override getVerification() to return a predefined result or a default success.
     */
    public function getVerification(string $verifierIdOrModelId): VerificationResult
    {
        if (isset($this->predefinedResults[$verifierIdOrModelId])) {
            $result = $this->predefinedResults[$verifierIdOrModelId];
            $this->completedVerifications[] = $result;

            return $result;
        }

        $result = self::fakeVerification();
        $this->completedVerifications[] = $result;

        return $result;
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    /**
     * Assert that at least one verification was started via create().
     */
    public function assertVerificationStarted(): void
    {
        Assert::assertNotEmpty(
            $this->createdVerifications,
            'No verifications were started.',
        );
    }

    /**
     * Assert that no verifications were started.
     */
    public function assertNothingStarted(): void
    {
        Assert::assertEmpty(
            $this->createdVerifications,
            'Expected no verifications to be started, but '.count($this->createdVerifications).' were started.',
        );
    }

    /**
     * Assert that at least one verification result was fetched.
     * An optional callback receives each VerificationResult for deeper inspection.
     *
     * @param  (callable(VerificationResult): bool)|null  $callback
     */
    public function assertVerificationCompleted(?callable $callback = null): void
    {
        Assert::assertNotEmpty(
            $this->completedVerifications,
            'No verifications were completed.',
        );

        if ($callback !== null) {
            foreach ($this->completedVerifications as $result) {
                $return = $callback($result);
                if ($return === true) {
                    return;
                }
            }

            Assert::fail('No completed verification matched the provided callback.');
        }
    }

    /**
     * Return all verifications that have been created during this test.
     *
     * @return list<PendingVerification>
     */
    public function createdVerifications(): array
    {
        return $this->createdVerifications;
    }
}
