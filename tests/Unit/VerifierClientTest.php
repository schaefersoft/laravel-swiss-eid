<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SwissEid\LaravelSwissEid\Exceptions\SwissEidException;
use SwissEid\LaravelSwissEid\Exceptions\VerifierConnectionException;
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

it('fetches an OAuth2 token and attaches it to verifier requests', function (): void {
    Cache::flush();

    Http::fake([
        'auth.example/token' => Http::response([
            'access_token' => 'token-from-server',
            'expires_in' => 3600,
        ], 200),
        'localhost:8083/management/api/verifications' => Http::response([
            'id' => 'verifier-uuid',
        ], 200),
    ]);

    $client = makeClient([
        'auth' => [
            'enabled' => true,
            'token_url' => 'http://auth.example/token',
            'client_id' => 'cid',
            'client_secret' => 'csecret',
        ],
    ]);

    $client->createVerification(['presentation_definition' => []]);

    Http::assertSent(fn (Request $req) => $req->url() === 'http://auth.example/token'
        && $req->method() === 'POST');

    Http::assertSent(fn (Request $req) => str_contains($req->url(), '/verifications')
        && $req->hasHeader('Authorization', 'Bearer token-from-server'));
});

it('reuses a cached OAuth2 token for subsequent requests', function (): void {
    Cache::flush();
    Cache::put('swiss_eid_oauth_token', 'cached-token', 60);

    Http::fake([
        'localhost:8083/management/api/verifications/abc' => Http::response(['state' => 'PENDING'], 200),
    ]);

    $client = makeClient([
        'auth' => [
            'enabled' => true,
            'token_url' => 'http://auth.example/token',
            'client_id' => 'cid',
            'client_secret' => 'csecret',
        ],
    ]);

    $client->getVerification('abc');

    Http::assertSent(fn (Request $req) => str_contains($req->url(), '/verifications/abc')
        && $req->hasHeader('Authorization', 'Bearer cached-token'));
    Http::assertNotSent(fn (Request $req) => str_contains($req->url(), 'auth.example'));
});

it('throws SwissEidException on 4xx when fetching a verification', function (): void {
    Http::fake([
        '*' => Http::response(['error' => 'Not found'], 404),
    ]);

    $client = makeClient();
    $client->getVerification('missing');
})->throws(SwissEidException::class);

it('throws VerifierConnectionException when verifier is unreachable on create', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('unreachable');
    });

    $client = makeClient();
    $client->createVerification([]);
})->throws(VerifierConnectionException::class);

it('throws VerifierConnectionException when verifier is unreachable on get', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('unreachable');
    });

    $client = makeClient();
    $client->getVerification('any');
})->throws(VerifierConnectionException::class);

it('throws VerifierConnectionException when token endpoint is unreachable', function (): void {
    Cache::flush();

    Http::fake(function (): void {
        throw new ConnectionException('token endpoint down');
    });

    $client = makeClient([
        'auth' => [
            'enabled' => true,
            'token_url' => 'http://auth.example/token',
            'client_id' => 'cid',
            'client_secret' => 'csecret',
        ],
    ]);

    $client->createVerification([]);
})->throws(VerifierConnectionException::class);
