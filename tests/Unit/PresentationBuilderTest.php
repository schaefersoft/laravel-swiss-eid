<?php

declare(strict_types=1);

use SwissEid\LaravelSwissEid\PresentationBuilder;

it('builds a valid presentation definition with vct constraint', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $result = $builder->build();

    expect($result)
        ->toHaveKey('presentation_definition')
        ->toHaveKey('response_mode', 'direct_post');

    $def = $result['presentation_definition'];
    expect($def)->toHaveKey('id')->toHaveKey('input_descriptors');

    $descriptor = $def['input_descriptors'][0];
    expect($descriptor['constraints']['fields'][0])
        ->toBe([
            'path' => ['$.vct'],
            'filter' => ['type' => 'string', 'const' => 'betaid-sdjwt'],
        ]);
});

it('adds age_over_18 field', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $builder->addAgeOver18();
    $result = $builder->build();

    $fields = $result['presentation_definition']['input_descriptors'][0]['constraints']['fields'];

    $paths = array_column($fields, 'path');
    expect($paths)->toContain(['$.age_over_18']);
});

it('adds age_over_16 field', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $builder->addAgeOver16();
    $result = $builder->build();

    $fields = $result['presentation_definition']['input_descriptors'][0]['constraints']['fields'];
    $paths = array_column($fields, 'path');

    expect($paths)->toContain(['$.age_over_16']);
});

it('adds arbitrary fields via addField()', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $builder->addField('$.given_name')->addField('$.family_name');
    $result = $builder->build();

    $fields = $result['presentation_definition']['input_descriptors'][0]['constraints']['fields'];
    $paths = array_column($fields, 'path');

    expect($paths)->toContain(['$.given_name'])->toContain(['$.family_name']);
});

it('does not duplicate fields', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $builder->addField('$.given_name')->addField('$.given_name');
    $result = $builder->build();

    $fields = $result['presentation_definition']['input_descriptors'][0]['constraints']['fields'];
    $names = array_map(fn ($f) => $f['path'][0] ?? '', $fields);

    expect(array_count_values($names)['$.given_name'])->toBe(1);
});

it('sets accepted issuer dids', function (): void {
    $dids = ['did:example:123', 'did:example:456'];
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $builder->setAcceptedIssuers($dids);
    $result = $builder->build();

    expect($result['accepted_issuer_dids'])->toBe($dids);
});

it('uses ES256 algorithms by default', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $result = $builder->build();

    $format = $result['presentation_definition']['input_descriptors'][0]['format']['vc+sd-jwt'];
    expect($format['sd-jwt_alg_values'])->toBe(['ES256']);
    expect($format['kb-jwt_alg_values'])->toBe(['ES256']);
});

it('generates unique UUIDs for each build()', function (): void {
    $builder = new PresentationBuilder(credentialType: 'betaid-sdjwt');
    $first = $builder->build();
    $second = $builder->build();

    expect($first['presentation_definition']['id'])
        ->not()->toBe($second['presentation_definition']['id']);
});
