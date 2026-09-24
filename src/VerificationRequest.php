<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid;

use SchaeferSoft\SwissEid\DTOs\PendingVerification;
use SchaeferSoft\SwissEid\Enums\CredentialField;
use SchaeferSoft\SwissEid\Exceptions\SwissEidException;
use SchaeferSoft\SwissEid\Exceptions\VerifierConnectionException;

/**
 * A single verification request. Every call to SwissEid::verify() returns a
 * fresh instance, so no state is shared between requests.
 */
class VerificationRequest
{
    private int|string|null $userId = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        private readonly SwissEidManager $manager,
        private readonly PresentationBuilder $builder,
    ) {}

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
        // PresentationBuilder normalises bare names ('given_name'), legacy
        // JSONPaths ('$.given_name') and dotted paths ('address.street').
        $this->builder->addField($path);

        return $this;
    }

    /**
     * Override the credential type(s) (vct).
     *
     * @param  string|list<string>  $type
     */
    public function credentialType(string|array $type): static
    {
        $this->builder->setCredentialType($type);

        return $this;
    }

    /**
     * Set the verification purpose (vqPS) registered at the trust infrastructure.
     * Strings are wrapped as the 'default' localization.
     *
     * @param  string|array<string, string>  $name
     * @param  string|array<string, string>  $description
     */
    public function purpose(string $scope, string|array $name, string|array $description): static
    {
        $this->builder->setVerificationPurpose([
            'scope' => $scope,
            'purpose_name' => is_string($name) ? ['default' => $name] : $name,
            'purpose_description' => is_string($description) ? ['default' => $description] : $description,
        ]);

        return $this;
    }

    /**
     * Override the response mode (e.g. 'direct_post.jwt' for encrypted wallet responses).
     */
    public function responseMode(string $mode): static
    {
        $this->builder->setResponseMode($mode);

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
     * Override the trust anchors accepted for this verification.
     *
     * @param  list<array{did: string, trust_registry_uri: string}>  $anchors
     */
    public function trustAnchors(array $anchors): static
    {
        $this->builder->setTrustAnchors($anchors);

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

    /**
     * Send the verification request to the swiyu verifier and persist the record.
     *
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function create(): PendingVerification
    {
        return $this->manager->start($this);
    }

    public function getBuilder(): PresentationBuilder
    {
        return $this->builder;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
