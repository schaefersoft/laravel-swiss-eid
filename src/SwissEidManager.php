<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid;

use Carbon\Carbon;
use Illuminate\Support\Str;
use SchaeferSoft\SwissEid\DTOs\PendingVerification;
use SchaeferSoft\SwissEid\DTOs\VerificationResult;
use SchaeferSoft\SwissEid\Enums\VerificationState;
use SchaeferSoft\SwissEid\Exceptions\SwissEidException;
use SchaeferSoft\SwissEid\Exceptions\VerificationNotFoundException;
use SchaeferSoft\SwissEid\Exceptions\VerifierConnectionException;
use SchaeferSoft\SwissEid\Models\EidVerification;

/**
 * Calls to builder methods (e.g. ageOver18()) start a new VerificationRequest.
 *
 * @mixin VerificationRequest
 */
class SwissEidManager
{
    public function __construct(
        private readonly VerifierClient $client,
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {}

    /**
     * Start a new, independent verification request.
     */
    public function verify(): VerificationRequest
    {
        return new VerificationRequest($this, $this->newBuilder());
    }

    /**
     * Send a verification request to the swiyu verifier and persist the record.
     *
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function start(VerificationRequest $request): PendingVerification
    {
        $builder = $request->getBuilder();
        $credentialTypes = $builder->getCredentialTypes();

        if ($credentialTypes === []) {
            throw new SwissEidException(
                'No credential type configured. Set the SWISS_EID_CREDENTIAL_TYPE environment variable or call credentialType().',
            );
        }

        $payload = $builder->build();
        $response = $this->client->createVerification($payload);

        $ttl = (int) ($this->config['verification_ttl'] ?? 300);
        $localId = Str::uuid()->toString();

        $verification = EidVerification::create([
            'id' => $localId,
            'verifier_id' => $response['id'] ?? $response['verificationId'] ?? '',
            'user_id' => $request->getUserId(),
            'state' => VerificationState::Pending,
            'credential_type' => implode(',', $credentialTypes),
            'requested_fields' => $payload['dcql_query']['credentials'][0]['claims'] ?? [],
            'metadata' => $request->getMetadata() ?: null,
            'deeplink' => $response['verification_deeplink'] ?? $response['deeplink'] ?? $response['verification_url'] ?? $response['verificationUrl'] ?? '',
            'verification_url' => $response['verification_url'] ?? $response['verificationUrl'] ?? $response['verification_deeplink'] ?? $response['deeplink'] ?? '',
            'expires_at' => Carbon::now()->addSeconds($ttl),
        ]);

        return new PendingVerification(
            id: $verification->id,
            verifierId: $verification->verifier_id,
            deeplink: (string) $verification->deeplink,
            verificationUrl: (string) $verification->verification_url,
            state: $verification->state->value,
            expiresAt: $verification->expires_at,
        );
    }

    /**
     * Retrieve the current result of a verification by its local UUID or verifier ID.
     *
     * @throws VerificationNotFoundException
     */
    public function getVerification(string $verifierIdOrModelId): VerificationResult
    {
        return $this->findVerification($verifierIdOrModelId)->toResult();
    }

    /**
     * Pull the current state of a verification from the swiyu verifier and
     * persist it. Use this when the webhook cannot reach your application.
     *
     * @throws VerificationNotFoundException
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function refresh(string $verifierIdOrModelId): VerificationResult
    {
        $verification = $this->findVerification($verifierIdOrModelId);

        return (new VerificationSynchronizer($this->client))->sync($verification)->toResult();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->verify()->{$method}(...$arguments);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * @throws VerificationNotFoundException
     */
    private function findVerification(string $verifierIdOrModelId): EidVerification
    {
        $verification = EidVerification::find($verifierIdOrModelId)
            ?? EidVerification::where('verifier_id', $verifierIdOrModelId)->first();

        if ($verification === null) {
            throw new VerificationNotFoundException(
                "Verification [{$verifierIdOrModelId}] not found.",
            );
        }

        return $verification;
    }

    private function newBuilder(): PresentationBuilder
    {
        $builder = new PresentationBuilder(
            credentialType: (string) ($this->config['credentials']['type'] ?? ''),
            responseMode: (string) ($this->config['verifier']['response_mode'] ?? 'direct_post.jwt'),
        );

        $issuers = array_values(array_filter(
            (array) ($this->config['credentials']['accepted_issuers'] ?? []),
            static fn ($did) => is_string($did) && $did !== '',
        ));

        if ($issuers !== []) {
            $builder->setAcceptedIssuers($issuers);
        }

        /** @var list<array{did: string, trust_registry_uri: string}> $anchors */
        $anchors = array_values(array_filter(
            (array) ($this->config['credentials']['trust_anchors'] ?? []),
            static fn ($anchor) => is_array($anchor) && $anchor !== [],
        ));

        if ($anchors !== []) {
            $builder->setTrustAnchors($anchors);
        }

        $purpose = $this->config['verification_purpose'] ?? null;

        if (is_array($purpose) && $purpose !== []) {
            $builder->setVerificationPurpose($purpose);
        }

        return $builder;
    }
}
