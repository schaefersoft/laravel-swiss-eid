<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

use Illuminate\Support\Str;

/**
 * Builds a DIF Presentation Exchange v2.1 presentation_definition
 * in the format expected by the swiyu verifier.
 */
class PresentationBuilder
{
    private string $credentialType;

    /** @var list<string> */
    private array $fields = [];

    /** @var list<string> */
    private array $acceptedIssuers = [];

    private string $sdJwtAlg = 'ES256';

    private string $kbJwtAlg = 'ES256';

    public function __construct(string $credentialType, string $sdJwtAlg = 'ES256', string $kbJwtAlg = 'ES256')
    {
        $this->credentialType = $credentialType;
        $this->sdJwtAlg = $sdJwtAlg;
        $this->kbJwtAlg = $kbJwtAlg;
    }

    /**
     * Add a JSON path field to the presentation request (e.g. '$.given_name').
     */
    public function addField(string $jsonPath): self
    {
        if (! in_array($jsonPath, $this->fields, true)) {
            $this->fields[] = $jsonPath;
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
     * Convenience: add the age_over_18 field.
     */
    public function addAgeOver18(): self
    {
        return $this->addField('$.age_over_18');
    }

    /**
     * Convenience: add the age_over_16 field.
     */
    public function addAgeOver16(): self
    {
        return $this->addField('$.age_over_16');
    }

    /**
     * Build the complete request payload for the swiyu verifier.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $constraintFields = [
            [
                'path' => ['$.vct'],
                'filter' => [
                    'type' => 'string',
                    'const' => $this->credentialType,
                ],
            ],
        ];

        foreach ($this->fields as $path) {
            $constraintFields[] = ['path' => [$path]];
        }

        return [
            'accepted_issuer_dids' => $this->acceptedIssuers,
            'response_mode' => 'direct_post',
            'presentation_definition' => [
                'id' => Str::uuid()->toString(),
                'input_descriptors' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'format' => [
                            'vc+sd-jwt' => [
                                'sd-jwt_alg_values' => [$this->sdJwtAlg],
                                'kb-jwt_alg_values' => [$this->kbJwtAlg],
                            ],
                        ],
                        'constraints' => [
                            'fields' => $constraintFields,
                        ],
                    ],
                ],
            ],
        ];
    }
}
