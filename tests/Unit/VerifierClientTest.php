<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SwissEid\LaravelSwissEid\Exceptions\SwissEidException;
use SwissEid\LaravelSwissEid\VerifierClient;

function makeClient(array $override = []): VerifierClient
{
    return new VerifierClient(array_merge([
        'verifier' => [
            'base_url' => 'http://localhost:8083',
            'management_path' => '/management/api',
            'timeout' => 10,
        ],
        'auth' => [
            'enabled' => false,
            'token_url' => null,
            'client_id' => null,
            'client_secret' => null,
        ],
    ], $override));
}

it('sends a POST to create a verification', function (): void {
    Http::fake([
        'localhost:8083/management/api/verifications' => Http::response([
            'id' => 'verifier-uuid',
            'deeplink' => 'openid-vc://start',
            'verificationUrl' => 'http://localhost:8083/verify/123',
        ], 200),
    ]);

    $client = makeClient();
    $result = $client->createVerification(['presentation_definition' => []]);

    expect($result['id'])->toBe('verifier-uuid');

    Http::assertSent(fn (Request $req) => str_contains($req->url(), '/verifications')
        && $req->method() === 'POST');
});

it('sends a GET to fetch a verification', function (): void {
    Http::fake([
        'localhost:8083/management/api/verifications/abc123' => Http::response([
            'state' => 'SUCCESS',
            'wallet_response' => ['credential_subject_data' => ['given_name' => 'Anna']],
        ], 200),
    ]);

    $client = makeClient();
    $result = $client->getVerification('abc123');

    expect($result['state'])->toBe('SUCCESS');
    expect($result['wallet_response']['credential_subject_data']['given_name'])->toBe('Anna');
});

it('throws SwissEidException on 4xx response', function (): void {
    Http::fake([
        '*' => Http::response(['error' => 'Bad Request'], 400),
    ]);

    $client = makeClient();
    $client->createVerification([]);
})->throws(SwissEidException::class);

it('throws SwissEidException on 5xx response', function (): void {
    Http::fake([
        '*' => Http::response(['error' => 'Server Error'], 500),
    ]);

    $client = makeClient();
    $client->createVerification([]);
})->throws(SwissEidException::class);
