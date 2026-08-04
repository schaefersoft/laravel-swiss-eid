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

    private string $credentialType;

    /**
     * Requested claims as DCQL path-segment arrays, e.g. ['given_name'] or
     * ['address', 'street_address'].
     *
     * @var list<list<string>>
     */
    private array $fields = [];

    /** @var list<string> */
    private array $acceptedIssuers = [];

    private string $responseMode;

    public function __construct(string $credentialType, string $responseMode = 'direct_post.jwt')
    {
        $this->credentialType = $credentialType;
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
     * Change the credential type (vct).
     */
    public function setCredentialType(string $vct): self
    {
        $this->credentialType = $vct;

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
                'vct_values' => [$this->credentialType],
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

        return [
            'accepted_issuer_dids' => $this->acceptedIssuers,
            'response_mode' => $this->responseMode,
            'dcql_query' => [
                'credentials' => [$credential],
            ],
        ];
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
