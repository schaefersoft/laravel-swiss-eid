<?php

declare(strict_types=1);

use SwissEid\LaravelSwissEid\PresentationBuilder;

it('builds a DCQL query with the vct in meta.vct_values', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $result = $builder->build();

    expect($result)
        ->toHaveKey('dcql_query')
        ->toHaveKey('response_mode', 'direct_post.jwt');

    $credential = $result['dcql_query']['credentials'][0];

    expect($credential['id'])->toBe('swiss_eid');
    expect($credential['format'])->toBe('dc+sd-jwt');
    expect($credential['meta']['vct_values'])->toBe(['test-sdjwt']);
});

it('supports multiple vct values as array and comma-separated string', function (): void {
    $fromArray = new PresentationBuilder(credentialType: ['betaid-sdjwt', 'urn:vct:ch.admin.bcs.betaid']);
    $fromString = new PresentationBuilder(credentialType: 'betaid-sdjwt, urn:vct:ch.admin.bcs.betaid');

    $expected = ['betaid-sdjwt', 'urn:vct:ch.admin.bcs.betaid'];

    expect($fromArray->build()['dcql_query']['credentials'][0]['meta']['vct_values'])->toBe($expected);
    expect($fromString->build()['dcql_query']['credentials'][0]['meta']['vct_values'])->toBe($expected);
});

it('adds age_over_18 as a DCQL claim path', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->addAgeOver18();
    $result = $builder->build();

    $claims = $result['dcql_query']['credentials'][0]['claims'];
    $paths = array_column($claims, 'path');

    expect($paths)->toContain(['age_over_18']);
});

it('adds age_over_16 as a DCQL claim path', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->addAgeOver16();
    $result = $builder->build();

    $claims = $result['dcql_query']['credentials'][0]['claims'];
    $paths = array_column($claims, 'path');

    expect($paths)->toContain(['age_over_16']);
});

it('normalises legacy JSONPath and bare-name fields to DCQL path segments', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->addField('$.given_name')->addField('family_name');
    $result = $builder->build();

    $claims = $result['dcql_query']['credentials'][0]['claims'];
    $paths = array_column($claims, 'path');

    expect($paths)->toContain(['given_name'])->toContain(['family_name']);
});

it('splits dotted paths into nested DCQL segments', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->addField('address.street_address');
    $result = $builder->build();

    $claims = $result['dcql_query']['credentials'][0]['claims'];
    $paths = array_column($claims, 'path');

    expect($paths)->toContain(['address', 'street_address']);
});

it('does not duplicate claims regardless of input notation', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->addField('$.given_name')->addField('given_name');
    $result = $builder->build();

    $claims = $result['dcql_query']['credentials'][0]['claims'];

    expect($claims)->toHaveCount(1);
});

it('omits the claims key when no fields are requested', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $result = $builder->build();

    expect($result['dcql_query']['credentials'][0])->not->toHaveKey('claims');
});

it('sets accepted issuer dids', function (): void {
    $dids = ['did:example:123', 'did:example:456'];
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->setAcceptedIssuers($dids);
    $result = $builder->build();

    expect($result['accepted_issuer_dids'])->toBe($dids);
});

it('uses direct_post.jwt response mode by default', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $result = $builder->build();

    expect($result['response_mode'])->toBe('direct_post.jwt');
});

it('allows overriding response mode to direct_post', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt');
    $builder->setResponseMode('direct_post');
    $result = $builder->build();

    expect($result['response_mode'])->toBe('direct_post');
});

it('accepts response mode via constructor', function (): void {
    $builder = new PresentationBuilder(credentialType: 'test-sdjwt', responseMode: 'direct_post');
    $result = $builder->build();

    expect($result['response_mode'])->toBe('direct_post');
});
