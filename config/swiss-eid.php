<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | swiyu Verifier Connection
    |--------------------------------------------------------------------------
    |
    | Configure the connection to the swiyu verifier service. The base_url
    | should point to your running verifier instance (e.g. Docker container).
    | The service has to be available publicly.
    |
    */
    'verifier' => [
        'base_url' => env('SWISS_EID_VERIFIER_URL', 'http://localhost:8083'),
        'management_path' => '/management/api',
        'timeout' => env('SWISS_EID_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | The verifier will POST to this webhook path when a verification
    | completes. Secure the endpoint with an API key.
    |
    */
    'webhook' => [
        'path' => env('SWISS_EID_WEBHOOK_PATH', '/swiss-eid/webhook'),
        'api_key_header' => env('SWISS_EID_WEBHOOK_KEY_HEADER', 'X-Verifier-Api-Key'),
        'api_key' => env('SWISS_EID_WEBHOOK_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credential Configuration
    |--------------------------------------------------------------------------
    |
    | Define which credential type to request and which issuers are accepted.
    | The accepted_issuers list should contain the DIDs of trusted issuers.
    |
    */
    'credentials' => [
        'type' => env('SWISS_EID_CREDENTIAL_TYPE'),
        'accepted_issuers' => array_filter(explode(',', (string) env('SWISS_EID_ACCEPTED_ISSUERS', ''))),
        'sd_jwt_alg' => 'ES256',
        'kb_jwt_alg' => 'ES256',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth2 Authentication
    |--------------------------------------------------------------------------
    |
    | If your verifier requires OAuth2 client credentials authentication,
    | enable this and provide the token endpoint and credentials.
    |
    */
    'auth' => [
        'enabled' => env('SWISS_EID_AUTH_ENABLED', false),
        'token_url' => env('SWISS_EID_TOKEN_URL'),
        'client_id' => env('SWISS_EID_CLIENT_ID'),
        'client_secret' => env('SWISS_EID_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Polling
    |--------------------------------------------------------------------------
    |
    | Enable the built-in polling endpoint that clients can query to check
    | verification state without server-sent events or WebSockets.
    |
    */
    'polling' => [
        'enabled' => env('SWISS_EID_POLLING_ENABLED', true),
        'route_path' => env('SWISS_EID_POLLING_PATH', '/swiss-eid/status'),
    ],

    /*
    |--------------------------------------------------------------------------
    | General Settings
    |--------------------------------------------------------------------------
    */

    /** Seconds until a pending verification expires. */
    'verification_ttl' => env('SWISS_EID_VERIFICATION_TTL', 300),

    /** Database table name for storing verification records. */
    'table_name' => env('SWISS_EID_TABLE_NAME', 'eid_verifications'),

    /** Column type for user_id: 'int' (unsignedBigInteger), 'uuid', or 'string'. */
    'user_id_type' => env('SWISS_EID_USER_ID_TYPE', 'int'),

];
