<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

/**
 * Builds a DCQL (Digital Credentials Query Language) query for the swiyu
 * verifier as specified by OpenID4VP 1.0.
 *
 * This replaces the legacy DIF Presentation Exchange `presentation_definition`,
 * which the swiyu Generic Verifier removed in 3.0.0 (DCQL is now enforced).
 */
class PresentationBuilder
{
    /** DCQL credential identifier; must match ^[a-zA-Z0-9_-]+$. */
    private const CREDENTIAL_ID = 'swiss_eid';

    /** SD-JWT VC credential format (OID4VP 1.0 / SD-JWT VC draft >= 06). */
    private const FORMAT = 'dc+sd-jwt';

    /** @var list<string> */
    private array $credentialTypes;

    /**
     * Requested claims as DCQL path-segment arrays, e.g. ['given_name'] or
     * ['address', 'street_address'].
     *
     * @var list<list<string>>
     */
    private array $fields = [];

    /** @var list<string> */
    private array $acceptedIssuers = [];

    /** @var list<array{did: string, trust_registry_uri: string}> */
    private array $trustAnchors = [];

    /** @var array<string, mixed>|null */
    private ?array $verificationPurpose = null;

    private string $responseMode;

    /**
     * @param  string|list<string>  $credentialType  One or more vct values; a string may be comma-separated.
     */
    public function __construct(string|array $credentialType, string $responseMode = 'direct_post.jwt')
    {
        $this->credentialTypes = $this->normaliseTypes($credentialType);
        $this->responseMode = $responseMode;
    }

    /**
     * Add a claim to the query. Accepts a bare claim name ('given_name'), a
     * legacy JSONPath ('$.given_name') or a dotted path ('address.street'),
     * all normalised to a DCQL path-segment array.
     */
    public function addField(string $path): self
    {
        $segments = $this->normalisePath($path);

        if ($segments !== [] && ! in_array($segments, $this->fields, true)) {
            $this->fields[] = $segments;
        }

        return $this;
    }

    /**
     * Change the credential type(s) (vct).
     *
     * @param  string|list<string>  $vct
     */
    public function setCredentialType(string|array $vct): self
    {
        $this->credentialTypes = $this->normaliseTypes($vct);

        return $this;
    }

    /**
     * Set the list of accepted issuer DIDs.
     *
     * @param  list<string>  $dids
     */
    public function setAcceptedIssuers(array $dids): self
    {
        $this->acceptedIssuers = $dids;

        return $this;
    }

    /**
     * Set trust anchors as an alternative to listing every accepted issuer DID.
     *
     * @param  list<array{did: string, trust_registry_uri: string}>  $anchors
     */
    public function setTrustAnchors(array $anchors): self
    {
        $this->trustAnchors = $anchors;

        return $this;
    }

    /**
     * Set the vqPS transparency metadata registered at the trust infrastructure:
     * ['scope' => ..., 'purpose_name' => [...], 'purpose_description' => [...]].
     *
     * @param  array<string, mixed>|null  $purpose
     */
    public function setVerificationPurpose(?array $purpose): self
    {
        $this->verificationPurpose = $purpose;

        return $this;
    }

    /**
     * Change the response mode (e.g. 'direct_post' or 'direct_post.jwt').
     */
    public function setResponseMode(string $mode): self
    {
        $this->responseMode = $mode;

        return $this;
    }

    /**
     * Convenience: add the age_over_18 claim.
     */
    public function addAgeOver18(): self
    {
        return $this->addField('age_over_18');
    }

    /**
     * Convenience: add the age_over_16 claim.
     */
    public function addAgeOver16(): self
    {
        return $this->addField('age_over_16');
    }

    /**
     * Build the complete request payload for the swiyu verifier.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $credential = [
            'id' => self::CREDENTIAL_ID,
            'format' => self::FORMAT,
            'meta' => [
                'vct_values' => $this->credentialTypes,
            ],
        ];

        // DCQL: omitting `claims` requests the whole credential; only add the
        // key when specific claims were requested.
        if ($this->fields !== []) {
            $credential['claims'] = array_map(
                static fn (array $segments): array => ['path' => $segments],
                $this->fields,
            );
        }

        $payload = [
            'accepted_issuer_dids' => $this->acceptedIssuers,
            'response_mode' => $this->responseMode,
            'dcql_query' => [
                'credentials' => [$credential],
            ],
        ];

        if ($this->trustAnchors !== []) {
            $payload['trust_anchors'] = $this->trustAnchors;
        }

        if ($this->verificationPurpose !== null) {
            $payload['verification_purpose'] = $this->verificationPurpose;
        }

        return $payload;
    }

    /**
     * @param  string|list<string>  $vct
     * @return list<string>
     */
    private function normaliseTypes(string|array $vct): array
    {
        $values = is_string($vct) ? explode(',', $vct) : $vct;

        return array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * Normalise a claim reference to a DCQL path-segment array.
     *
     * @return list<string>
     */
    private function normalisePath(string $path): array
    {
        // Strip a leading JSONPath root ('$.foo' / '$foo'), then split on dots.
        $normalised = ltrim($path, '$.');

        return array_values(array_filter(
            explode('.', $normalised),
            static fn (string $segment): bool => $segment !== '',
        ));
    }
}
