# Laravel Swiss eID

[![Tests](https://github.com/schaefersoft/laravel-swiss-eid/actions/workflows/tests.yml/badge.svg)](https://github.com/schaefersoft/laravel-swiss-eid/actions)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://phpstan.org)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/schaefersoft/laravel-swiss-eid.svg)](https://packagist.org/packages/schaefersoft/laravel-swiss-eid)
[![License](https://img.shields.io/packagist/l/schaefersoft/laravel-swiss-eid.svg)](LICENSE)

Laravel package for integrating the Swiss eID (**swiyu**) verification flow: builds
the OpenID4VP / DIF Presentation Exchange request, talks to a swiyu Verifier,
persists the verification, handles the webhook callback and emits events. UI is
intentionally left to you — the package only exposes data primitives (QR code
SVG, deeplink, JSON status endpoint) so you can render in Blade, Livewire,
Vue, React, or anything else.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
    - [PHP & Laravel versions](#1-php--laravel-versions)
    - [A running swiyu Verifier](#2-a-running-swiyu-verifier)
    - [Public reachability & webhook routing](#3-public-reachability--webhook-routing)
    - [Verifier signing key (PEM)](#4-verifier-signing-key-pem)
    - [Accepted issuer DIDs](#5-accepted-issuer-dids)
    - [Swiss wallet app (for testing)](#6-swiss-wallet-app-for-testing)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)
5. [Database](#database)
6. [Artisan Commands](#artisan-commands)
7. [Testing](#testing)
8. [Troubleshooting](#troubleshooting)
9. [Contributing](#contributing)
10. [License](#license)

---

## Prerequisites

Before installing the package you need a few pieces of Swiss eID infrastructure
in place. Each sub-section below covers exactly one requirement.

### 1. PHP & Laravel versions

| Dependency | Version |
|---|---|
| PHP | `8.1+` (runtime); CI tests `8.2`–`8.5` |
| Laravel | `10`, `11`, `12`, `13` |
| Composer | 2.x |

The package itself runs on PHP 8.1+ (uses native enums, readonly DTOs and
`HasUuids`). CI only tests against PHP 8.2+ because the Pest/PHPUnit dev
toolchain no longer resolves on PHP 8.1. Consumers on PHP 8.1 can still
install and use the package without issue.

### 2. A running swiyu Verifier

The Swiss eID ecosystem separates the **relying party** (your Laravel app) from
the **verifier service**, a small Spring Boot process that speaks OpenID4VP to
the wallet. This package is a **client of that verifier** — it does not
implement the OpenID4VP protocol itself.

You need to run the official verifier locally (or host it somewhere) before
this package can do anything:

- Repository: <https://github.com/e-id-admin/eidch-verifier-agent-oid4vp>
- Default port: `8083`
- Expected API base path: `/management/api`

A minimal `docker-compose.yml` typically looks like this (see the upstream
repo for the full example):

```yaml
services:
  swiyu-verifier:
    image: swiyu-verifier:local
    ports:
      - "8083:8080"
    environment:
      SWIYU_VERIFIER_DID: "did:tdw:...your-verifier-did..."
      SWIYU_SIGNING_KEY: |
        -----BEGIN EC PRIVATE KEY-----
        ...
        -----END EC PRIVATE KEY-----
      LARAVEL_WEBHOOK_URL: "http://your-laravel-host/swiss-eid/webhook"
      LARAVEL_WEBHOOK_API_KEY: "your-secret-key-here"
```

Confirm the verifier is reachable:

```bash
curl http://localhost:8083/management/api/verifications/anything
# 404 is fine — the connection works and the API responds.
```

### 3. Public reachability & webhook routing

The verification flow requires **two** network paths that both have to work:

1. **Wallet → Verifier**: the swiyu wallet on the user's phone fetches the
   presentation request from the verifier's public URL. During local
   development, expose the verifier via a tunnel (e.g. ngrok, Cloudflare
   Tunnel):

   ```bash
   ngrok http 8083
   # → https://something.ngrok-free.dev
   ```

   Put that tunnel URL into your Laravel `.env` as `SWISS_EID_VERIFIER_URL`.
   The verifier itself also needs to know its public URL in its own
   configuration so it can embed it in the QR-code deeplink.

2. **Verifier → Laravel webhook**: when the wallet has responded, the verifier
   POSTs to your Laravel app. If both run in Docker, the verifier must be able
   to resolve your Laravel host. Two common options:

   - Run the verifier on the **same Docker network** as your Laravel app
     (e.g. the DDEV project network) and use the internal hostname in
     `LARAVEL_WEBHOOK_URL`, such as `http://ddev-myproject-web/swiss-eid/webhook`.
   - Or expose Laravel publicly too (another tunnel) and use that URL.

   The webhook is authenticated via a shared secret — the verifier sends
   `X-Verifier-Api-Key: <key>` and the package's middleware rejects everything
   else with `401`.

### 4. Verifier signing key (PEM)

The verifier signs the presentation request it hands to the wallet. It expects
an **EC P-256 private key in PEM format**. Generate one with OpenSSL:

```bash
openssl ecparam -name prime256v1 -genkey -noout -out verifier-key.pem
# Public key (publish as part of your DID document):
openssl ec -in verifier-key.pem -pubout -out verifier-pub.pem
```

When passing the private key through a `.env` file, keep the real newlines
intact. Use a double-quoted multi-line value — **not** `\n` escape sequences,
which the verifier will refuse to parse:

```env
SWIYU_SIGNING_KEY="-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIE+...
-----END EC PRIVATE KEY-----"
```

### 5. Accepted issuer DIDs

The Swiss eID beta trust infrastructure uses `did:tdw:` identifiers. For a
verification to succeed, the credential presented by the wallet must be issued
by a DID that your verifier **trusts**. In practice you will list at least:

- Your **own** verifier DID (useful for self-issued test credentials).
- The **official Beta-ID issuer DID** if you want to accept real beta credentials.

Example for `.env` (comma-separated):

```env
SWISS_EID_ACCEPTED_ISSUERS=did:tdw:Qm...your-verifier:...,did:tdw:QmPEZPhDFR4nEYSFK5bMnvECqdpf1tPTPJuWs9QrMjCumw:identifier-reg.trust-infra.swiyu-int.admin.ch:api:v1:did:9a5559f0-b81c-4368-a170-e7b4ae424527
```

If you do not know the issuer DID of a credential, you can extract it from a
decoded SD-JWT (the `iss` field) or from the verifier logs when it rejects a
presentation with `issuer_not_accepted`.

### 6. Swiss wallet app (for testing)

To actually scan a QR code and complete a verification end-to-end you need the
**swiyu wallet** app installed on a phone:

- iOS: App Store — search "swiyu"
- Android: Google Play — search "swiyu"

For beta/integration testing against `trust-infra.swiyu-int.admin.ch`, the app
must be in the corresponding environment. Check the official documentation at
<https://www.eid.admin.ch> for environment-specific instructions.

---

## Installation

```bash
composer require schaefersoft/laravel-swiss-eid
```

Run the installer:

```bash
php artisan swiss-eid:install
```

This publishes the config file, the migration, prints the required `.env`
variables, and (optionally) runs `php artisan migrate`.

Manual alternative:

```bash
php artisan vendor:publish --tag=swiss-eid-config
php artisan vendor:publish --tag=swiss-eid-migrations
php artisan migrate
```

---

## Configuration

All settings live in `config/swiss-eid.php` and can be overridden with
environment variables:

| Variable | Default | Description |
|---|---|---|
| `SWISS_EID_VERIFIER_URL` | `http://localhost:8083` | Base URL of the swiyu verifier (must be reachable from Laravel). |
| `SWISS_EID_TIMEOUT` | `10` | HTTP timeout (seconds) for calls to the verifier. |
| `SWISS_EID_WEBHOOK_PATH` | `/swiss-eid/webhook` | Route the verifier POSTs to when a wallet has responded. |
| `SWISS_EID_WEBHOOK_KEY_HEADER` | `X-Verifier-Api-Key` | HTTP header carrying the shared webhook secret. |
| `SWISS_EID_WEBHOOK_API_KEY` | – | Shared secret; **required** — the middleware returns 401 without it. |
| `SWISS_EID_CREDENTIAL_TYPE` | `betaid-sdjwt` | Credential type (`vct`) to request from the wallet. |
| `SWISS_EID_ACCEPTED_ISSUERS` | – | Comma-separated list of accepted issuer DIDs. At least one is required. |
| `SWISS_EID_VERIFICATION_TTL` | `300` | Seconds a pending verification stays valid before being marked `expired`. |
| `SWISS_EID_POLLING_ENABLED` | `true` | Enable the built-in `/swiss-eid/status/{id}` JSON endpoint. |
| `SWISS_EID_POLLING_PATH` | `/swiss-eid/status` | Route prefix of the polling endpoint. |
| `SWISS_EID_AUTH_ENABLED` | `false` | Enable OAuth2 client-credentials auth against the verifier's management API. |
| `SWISS_EID_TOKEN_URL` | – | OAuth2 token endpoint (only if auth is enabled). |
| `SWISS_EID_CLIENT_ID` | – | OAuth2 client ID (only if auth is enabled). |
| `SWISS_EID_CLIENT_SECRET` | – | OAuth2 client secret (only if auth is enabled). |
| `SWISS_EID_TABLE_NAME` | `eid_verifications` | Override the DB table name if it clashes with your schema. |

---

## Usage

### Starting a verification

Use the `SwissEid` facade's fluent builder. Each call returns the same manager
instance, so you can chain freely. `create()` persists the record and returns
a `PendingVerification` DTO.

```php
use SwissEid\LaravelSwissEid\Facades\SwissEid;

$pending = SwissEid::verify()
    ->ageOver18()
    ->forUser($user->id)
    ->metadata(['order_id' => $order->id])
    ->create();
```

The returned `$pending` has:

| Property / method | Description |
|---|---|
| `$pending->id` | Internal UUID of the DB record. Use this to look it up later. |
| `$pending->verifierId` | ID assigned by the swiyu verifier. |
| `$pending->deeplink` | Universal link — open on the user's phone to launch the wallet. |
| `$pending->verificationUrl` | Full URL of the presentation request (for debugging). |
| `$pending->qrCode(int $size = 300)` | SVG string. Embed with `{!! !!}`. |
| `$pending->qrCodeDataUri(int $size = 300)` | `data:image/svg+xml;base64,...` for `<img src>`. |
| `$pending->statusUrl()` | URL of the JSON polling endpoint for this verification. |
| `$pending->expiresAt` | Carbon instance — TTL cutoff. |
| `$pending->isExpired()` | Quick boolean check. |

### Requesting specific fields

Use the `CredentialField` enum (preferred) or plain field-name strings:

```php
use SwissEid\LaravelSwissEid\Enums\CredentialField;

$pending = SwissEid::verify()
    ->fields([
        CredentialField::GivenName,
        CredentialField::FamilyName,
        CredentialField::DateOfBirth,
        CredentialField::Nationality,
    ])
    ->create();
```

Available cases: `AgeOver18`, `AgeOver16`, `GivenName`, `FamilyName`,
`DateOfBirth` (resolves to the JSON key `birth_date`), `Nationality`,
`PlaceOfBirth`, `Gender`.

You can also pass a single field by name or full JSON path:

```php
SwissEid::verify()->field('given_name');     // resolves to $.given_name
SwissEid::verify()->field('$.custom_path');  // passed through verbatim
```

### Overriding credential type / accepted issuers per request

```php
SwissEid::verify()
    ->credentialType('betaid-sdjwt')
    ->acceptedIssuers([
        'did:tdw:QmPEZ...your-trusted-issuer',
    ])
    ->ageOver18()
    ->create();
```

### Presenting to the user

The package is UI-agnostic. Render the primitives in any frontend:

```blade
{{-- Plain Blade, no JS framework required --}}
<div>
    {!! $pending->qrCode(300) !!}
    <a href="{{ $pending->deeplink }}">Open in Swiss Wallet App</a>
    <small>Polling: {{ $pending->statusUrl() }}</small>
</div>
```

```js
// React / Vue / vanilla JS: poll the JSON endpoint
const response = await fetch(statusUrl);
const { state, label, is_terminal } = await response.json();
if (is_terminal && state === 'success') { /* redirect */ }
```

The polling endpoint returns JSON:

```json
{
    "state": "pending | success | failed | expired",
    "label": "Ausstehend | Erfolgreich | Fehlgeschlagen | Abgelaufen",
    "is_terminal": false
}
```

Poll every 2–3 seconds until `is_terminal === true`. If the TTL has passed,
the endpoint will also flip `pending` → `expired` on the first call after
expiry and dispatch the `VerificationExpired` event.

### Handling the webhook

The webhook route (`POST /swiss-eid/webhook` by default) is registered
automatically by the package's service provider. It:

1. Validates the `X-Verifier-Api-Key` header via middleware.
2. Reads the `verification_id` from the payload.
3. Calls the verifier's GET endpoint to fetch the full result.
4. Writes `state`, `credential_data` (encrypted at rest) and
   `webhook_received_at` to the DB.
5. Dispatches `VerificationCompleted` or `VerificationFailed`.

You do **not** register this route yourself. Just make sure:

- `SWISS_EID_WEBHOOK_API_KEY` matches what the verifier sends.
- Your firewall / reverse proxy allows the verifier to reach that path.
- CSRF is not a concern — the route lives in the `api` middleware group.

### Retrieving results

```php
$result = SwissEid::getVerification($pending->id); // or the verifier ID

if ($result->isSuccessful()) {
    $firstName = $result->get('given_name');
    $isAdult   = $result->isAdult();          // age_over_18 convenience
    $raw       = $result->credentialData;     // decrypted array, or null
}
```

`VerificationResult` methods:

| Method | Returns |
|---|---|
| `isSuccessful()` / `isFailed()` / `isPending()` | bool |
| `get(string $field, mixed $default = null)` | Field from credential data. |
| `has(string $field)` | bool |
| `isAdult()` | Shortcut for `age_over_18 === true`. |
| `toArray()` | Plain array (for JSON responses). |

If the ID is not found a `VerificationNotFoundException` is thrown.

### Events

Listen in your `EventServiceProvider` or with `#[AsEventListener]`:

```php
use SwissEid\LaravelSwissEid\Events\VerificationCompleted;
use SwissEid\LaravelSwissEid\Events\VerificationFailed;
use SwissEid\LaravelSwissEid\Events\VerificationExpired;

Event::listen(VerificationCompleted::class, function ($event) {
    $user = User::find($event->verification->user_id);
    $user->update(['verified_at' => now()]);
});
```

Each event carries a single `$verification` property of type
`EidVerification` (the Eloquent model). Use its `toResult()` method if you
want the DTO view.

---

## Database

The package ships a single table (default name `eid_verifications`) with:

| Column | Type | Notes |
|---|---|---|
| `id` | UUID (PK) | Your internal ID — the one you hand to the frontend. |
| `verifier_id` | string | The ID returned by the swiyu verifier. |
| `user_id` | nullable | Your user reference (int or string). |
| `state` | enum | `pending`, `success`, `failed`, `expired`. |
| `credential_type` | string | Mirrors `SWISS_EID_CREDENTIAL_TYPE`. |
| `requested_fields` | json | The presentation-definition fields you requested. |
| `credential_data` | encrypted json | Decrypted automatically by the cast. |
| `metadata` | json, nullable | Anything you passed via `->metadata([...])`. |
| `deeplink`, `verification_url` | string | Cached from the verifier response. |
| `webhook_received_at` | datetime, nullable | Set when the webhook fires. |
| `expires_at` | datetime | TTL cutoff. |
| `created_at`, `updated_at` | datetime | Standard. |

Useful Eloquent scopes on `EidVerification`:

```php
EidVerification::pending()->get();
EidVerification::expired()->get();
EidVerification::forUser($user->id)->latest()->first();
```

Override the table name with `SWISS_EID_TABLE_NAME` in `.env`. The migration
and the model both read from `config('swiss-eid.table_name')`, so renaming is a
single-line change.

---

## Artisan Commands

| Command | Description |
|---|---|
| `swiss-eid:install` | Publish config + migration, print required `.env` variables, optionally run migrations. |
| `swiss-eid:test-connection` | Probe the verifier to confirm it is reachable and responding. |
| `swiss-eid:cleanup --days=7` | Delete expired records older than N days. Accepts `--dry-run`. |

Schedule the cleanup in `App\Console\Kernel` (or `routes/console.php` on
Laravel 11+):

```php
$schedule->command('swiss-eid:cleanup --days=30')->daily();
```

---

## Testing

Use the built-in `SwissEidFake` to avoid real HTTP calls in your tests:

```php
use SwissEid\LaravelSwissEid\Facades\SwissEid;
use SwissEid\LaravelSwissEid\SwissEidFake;

it('starts a verification', function () {
    $fake = SwissEid::fake();

    // ... code that calls SwissEid::verify()->ageOver18()->create()

    $fake->assertVerificationStarted();
});

it('reacts to a completed verification', function () {
    $result = SwissEidFake::fakeVerification(state: 'success', data: [
        'given_name'  => 'Anna',
        'age_over_18' => true,
    ]);

    $fake = SwissEid::fake([$result->id => $result]);

    SwissEid::getVerification($result->id);

    $fake->assertVerificationCompleted(fn ($r) => $r->get('given_name') === 'Anna');
});
```

Run the package's own test suite:

```bash
composer test
composer test:coverage   # Pest + min. 80% coverage
composer analyse         # PHPStan level 8
```

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `createVerificationManagementDto: Either acceptedIssuerDids or trustAnchors must be set` | `SWISS_EID_ACCEPTED_ISSUERS` is empty. | Set at least one DID. |
| Empty `deeplink` / no QR code | The verifier's response used different key casing (e.g. `verification_deeplink`). | The package already falls back through several aliases; check `storage/logs/laravel.log` for the raw response if a new one appears. |
| Verifier returns 500 on `/oid4vp/api/request-object/...` | PEM signing key is malformed (literal `\n` instead of real newlines). | Use double-quoted multi-line `.env` value — see the [signing key section](#4-verifier-signing-key-pem). |
| Webhook returns 500, logs say `getaddrinfo for db failed` | Verifier container cannot resolve your Laravel/DB host. | Join the verifier to the same Docker network as your Laravel app; use the internal hostname in `LARAVEL_WEBHOOK_URL`. |
| Webhook never fires (404) | Stale verification IDs in retry queue from a previous failed run. | Create a fresh verification — the verifier drops stale webhook retries after a while. |
| State is always `failed` with `issuer_not_accepted` | The credential's issuer DID is not in `SWISS_EID_ACCEPTED_ISSUERS`. | Add the real issuer DID (extract from wallet logs / decoded SD-JWT). |
| Wallet shows "Kein passender Nachweis verfügbar" | Requested field name does not match the credential schema (e.g. `date_of_birth` vs `birth_date`). | Use the `CredentialField` enum — the package maps the correct keys. |

Quick sanity check:

```bash
php artisan swiss-eid:test-connection
```

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Run `composer test` and `composer analyse` — both must pass.
4. Open a Pull Request. Conventional-commit messages are appreciated; they drive
   the automated release workflow.

---

## License

MIT. See [LICENSE](LICENSE) for details.


Trigger