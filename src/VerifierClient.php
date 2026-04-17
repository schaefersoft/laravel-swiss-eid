<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SwissEid\LaravelSwissEid\Exceptions\SwissEidException;
use SwissEid\LaravelSwissEid\Exceptions\VerifierConnectionException;

class VerifierClient
{
    /** Cache key for the OAuth2 access token. */
    private const TOKEN_CACHE_KEY = 'swiss_eid_oauth_token';

    public function __construct(
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {}

    /**
     * Send a verification creation request to the swiyu verifier.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function createVerification(array $payload): array
    {
        try {
            $response = $this->request()
                ->post($this->managementUrl('/verifications'), $payload)
                ->throw();

            return $response->json();
        } catch (RequestException $e) {
            throw new SwissEidException(
                'The verifier returned an error response: '.$e->getMessage(),
                $e->response->status(),
                $e,
            );
        } catch (ConnectionException $e) {
            throw new VerifierConnectionException(
                'Could not connect to the swiyu verifier: '.$e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Fetch the current state of a verification from the swiyu verifier.
     *
     * @return array<string, mixed>
     *
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function getVerification(string $id): array
    {
        try {
            $response = $this->request()
                ->get($this->managementUrl('/verifications/'.$id))
                ->throw();

            return $response->json();
        } catch (RequestException $e) {
            throw new SwissEidException(
                'The verifier returned an error response: '.$e->getMessage(),
                $e->response->status(),
                $e,
            );
        } catch (ConnectionException $e) {
            throw new VerifierConnectionException(
                'Could not connect to the swiyu verifier: '.$e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Build the base HTTP request with auth headers and timeout.
     */
    private function request(): PendingRequest
    {
        $request = Http::baseUrl($this->config['verifier']['base_url'])
            ->timeout((int) $this->config['verifier']['timeout'])
            ->acceptJson()
            ->asJson();

        if ($this->config['auth']['enabled']) {
            $token = $this->fetchAccessToken();
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * Build the full management API URL for the given path.
     */
    private function managementUrl(string $path): string
    {
        return rtrim((string) $this->config['verifier']['management_path'], '/').'/'.ltrim($path, '/');
    }

    /**
     * Retrieve an OAuth2 access token, using the cache to avoid redundant requests.
     *
     * @throws VerifierConnectionException
     */
    private function fetchAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) $this->config['verifier']['timeout'])
                ->post((string) $this->config['auth']['token_url'], [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config['auth']['client_id'],
                    'client_secret' => $this->config['auth']['client_secret'],
                ])
                ->throw();

            $data = $response->json();
            $accessToken = (string) ($data['access_token'] ?? '');
            $expiresIn = (int) ($data['expires_in'] ?? 3600);

            // Cache with a small buffer to avoid using an about-to-expire token
            Cache::put(self::TOKEN_CACHE_KEY, $accessToken, $expiresIn - 30);

            return $accessToken;
        } catch (ConnectionException $e) {
            throw new VerifierConnectionException(
                'Could not fetch OAuth2 access token: '.$e->getMessage(),
                0,
                $e,
            );
        }
    }
}
