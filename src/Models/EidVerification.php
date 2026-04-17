<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\VerificationState;

/**
 * @property string $id
 * @property string $verifier_id
 * @property int|string|null $user_id
 * @property VerificationState $state
 * @property string $credential_type
 * @property array<string, mixed> $requested_fields
 * @property array<string, mixed>|null $credential_data
 * @property array<string, mixed>|null $metadata
 * @property string|null $deeplink
 * @property string|null $verification_url
 * @property Carbon|null $webhook_received_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EidVerification extends Model
{
    use HasUuids;

    /** @var string */
    protected $primaryKey = 'id';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'verifier_id',
        'user_id',
        'state',
        'credential_type',
        'requested_fields',
        'credential_data',
        'metadata',
        'deeplink',
        'verification_url',
        'webhook_received_at',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'requested_fields' => 'array',
        'credential_data' => 'encrypted:array',
        'metadata' => 'array',
        'state' => VerificationState::class,
        'expires_at' => 'datetime',
        'webhook_received_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('swiss-eid.table_name', 'eid_verifications');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filter verifications that are still awaiting a wallet response.
     *
     * @param  Builder<EidVerification>  $query
     * @return Builder<EidVerification>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('state', VerificationState::Pending->value);
    }

    /**
     * Filter verifications whose TTL has passed.
     *
     * @param  Builder<EidVerification>  $query
     * @return Builder<EidVerification>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Filter verifications belonging to a specific user.
     *
     * @param  Builder<EidVerification>  $query
     * @return Builder<EidVerification>
     */
    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Whether this verification is still awaiting a wallet response.
     */
    public function isPending(): bool
    {
        return $this->state === VerificationState::Pending;
    }

    /**
     * Whether the wallet presented a valid credential.
     */
    public function isSuccessful(): bool
    {
        return $this->state === VerificationState::Success;
    }

    /**
     * Whether the verification TTL has passed and it was never completed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Wrap this model in a VerificationResult DTO.
     */
    public function toResult(): VerificationResult
    {
        return new VerificationResult(
            id: $this->id,
            state: $this->state,
            credentialData: $this->credential_data,
            model: $this,
        );
    }
}
