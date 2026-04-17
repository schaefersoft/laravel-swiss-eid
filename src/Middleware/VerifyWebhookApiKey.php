<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookApiKey
{
    /**
     * Validate that the incoming request carries the configured API key.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('swiss-eid.webhook.api_key_header', 'X-Verifier-Api-Key');
        $configuredKey = (string) config('swiss-eid.webhook.api_key', '');
        $incomingKey = (string) $request->header($header, '');

        if ($configuredKey === '' || ! hash_equals($configuredKey, $incomingKey)) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
