<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\DTOs;

use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Models\EidVerification;

class VerificationResult
{
    /**
     * @param  array<string, mixed>|null  $credentialData  Decrypted credential subject data, null if not yet available.
     */
    public function __construct(
        /** Our internal DB UUID. */
        public readonly string $id,
        /** The current state of this verification. */
        public readonly VerificationState $state,
        public readonly ?array $credentialData,
        /** The underlying Eloquent model. */
        public readonly EidVerification $model,
    ) {}

    /**
     * Whether the verification completed successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->state === VerificationState::Success;
    }

    /**
     * Whether the verification failed or expired.
     */
    public function isFailed(): bool
    {
        return $this->state === VerificationState::Failed
            || $this->state === VerificationState::Expired;
    }

    /**
     * Whether the verification is still awaiting a wallet response.
     */
    public function isPending(): bool
    {
        return $this->state === VerificationState::Pending;
    }

    /**
     * Retrieve a field from the credential data.
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return $this->credentialData[$field] ?? $default;
    }

    /**
     * Check whether a field exists in the credential data.
     */
    public function has(string $field): bool
    {
        return isset($this->credentialData[$field]);
    }

    /**
     * Convenience: check if the holder confirmed they are 18 or older.
     */
    public function isAdult(): bool
    {
        return $this->get('age_over_18') === true
            || $this->get('age_over_18') === 'true';
    }

    /**
     * Return all result data as a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state->value,
            'is_successful' => $this->isSuccessful(),
            'credential_data' => $this->credentialData,
        ];
    }
}
