<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

use Carbon\Carbon;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\DTOs\PendingVerification;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\CredentialField;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Exceptions\SwissEidException;
use SwissEid\LaravelSwissEid\Exceptions\VerificationNotFoundException;
use SwissEid\LaravelSwissEid\Exceptions\VerifierConnectionException;
use SwissEid\LaravelSwissEid\Models\EidVerification;

class SwissEidManager
{
    private PresentationBuilder $builder;

    private int|string|null $userId = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        private readonly VerifierClient $client,
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {
        $this->builder = $this->newBuilder();
    }

    // -------------------------------------------------------------------------
    // Fluent builder API
    // -------------------------------------------------------------------------

    /**
     * Start a new verification request. Resets all previously configured options.
     */
    public function verify(): static
    {
        $this->builder = $this->newBuilder();
        $this->userId = null;
        $this->metadata = [];

        return $this;
    }

    /**
     * Request the age_over_18 claim.
     */
    public function ageOver18(): static
    {
        $this->builder->addAgeOver18();

        return $this;
    }

    /**
     * Request the age_over_16 claim.
     */
    public function ageOver16(): static
    {
        $this->builder->addAgeOver16();

        return $this;
    }

    /**
     * Request multiple credential fields by their string names or enum values.
     *
     * @param  array<int, string|CredentialField>  $fields
     */
    public function fields(array $fields): static
    {
        foreach ($fields as $field) {
            $this->field($field instanceof CredentialField ? $field->value : $field);
        }

        return $this;
    }

    /**
     * Request a single credential field by name (e.g. 'given_name') or JSON path.
     */
    public function field(string $path): static
    {
        // Allow passing bare field names like 'given_name' as well as full paths
        $jsonPath = str_starts_with($path, '$') ? $path : '$.'.$path;
        $this->builder->addField($jsonPath);

        return $this;
    }

    /**
     * Override the credential type (vct).
     */
    public function credentialType(string $type): static
    {
        $this->builder->setCredentialType($type);

        return $this;
    }

    /**
     * Override the list of accepted issuer DIDs.
     *
     * @param  list<string>  $dids
     */
    public function acceptedIssuers(array $dids): static
    {
        $this->builder->setAcceptedIssuers($dids);

        return $this;
    }

    /**
     * Associate the resulting verification with a user ID.
     */
    public function forUser(int|string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Attach arbitrary metadata that will be stored with the verification record.
     *
     * @param  array<string, mixed>  $data
     */
    public function metadata(array $data): static
    {
        $this->metadata = array_merge($this->metadata, $data);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Terminal methods
    // -------------------------------------------------------------------------

    /**
     * Send the verification request to the swiyu verifier and persist the record.
     *
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function create(): PendingVerification
    {
        $payload = $this->builder->build();
        $response = $this->client->createVerification($payload);

        $ttl = (int) ($this->config['verification_ttl'] ?? 300);
        $localId = Str::uuid()->toString();

        $verification = EidVerification::create([
            'id' => $localId,
            'verifier_id' => $response['id'] ?? $response['verificationId'] ?? '',
            'user_id' => $this->userId,
            'state' => VerificationState::Pending,
            'credential_type' => $this->config['credentials']['type'],
            'requested_fields' => $payload['presentation_definition']['input_descriptors'][0]['constraints']['fields'] ?? [],
            'metadata' => $this->metadata ?: null,
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
        $verification = EidVerification::find($verifierIdOrModelId)
            ?? EidVerification::where('verifier_id', $verifierIdOrModelId)->first();

        if ($verification === null) {
            throw new VerificationNotFoundException(
                "Verification [{$verifierIdOrModelId}] not found.",
            );
        }

        return $verification->toResult();
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function newBuilder(): PresentationBuilder
    {
        $builder = new PresentationBuilder(
            credentialType: (string) ($this->config['credentials']['type'] ?? 'betaid-sdjwt'),
            sdJwtAlg: (string) ($this->config['credentials']['sd_jwt_alg'] ?? 'ES256'),
            kbJwtAlg: (string) ($this->config['credentials']['kb_jwt_alg'] ?? 'ES256'),
        );

        $issuers = array_values(array_filter(
            (array) ($this->config['credentials']['accepted_issuers'] ?? []),
            static fn ($did) => is_string($did) && $did !== '',
        ));

        if ($issuers !== []) {
            $builder->setAcceptedIssuers($issuers);
        }

        return $builder;
    }
}
