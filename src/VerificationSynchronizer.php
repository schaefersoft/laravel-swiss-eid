<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid;

use Illuminate\Support\Facades\Log;
use SchaeferSoft\SwissEid\Enums\VerificationState;
use SchaeferSoft\SwissEid\Events\VerificationCompleted;
use SchaeferSoft\SwissEid\Events\VerificationExpired;
use SchaeferSoft\SwissEid\Events\VerificationFailed;
use SchaeferSoft\SwissEid\Exceptions\SwissEidException;
use SchaeferSoft\SwissEid\Exceptions\VerifierConnectionException;
use SchaeferSoft\SwissEid\Models\EidVerification;

class VerificationSynchronizer
{
    public function __construct(
        private readonly VerifierClient $client,
    ) {}

    /**
     * Pull the current state of a verification from the swiyu verifier, persist
     * it and dispatch the matching event. Terminal verifications are left alone.
     *
     * @param  array<string, mixed>  $attributes  Additional attributes stored when the state changes.
     *
     * @throws SwissEidException
     * @throws VerifierConnectionException
     */
    public function sync(EidVerification $verification, array $attributes = []): EidVerification
    {
        if ($verification->state->isTerminal()) {
            return $verification;
        }

        // Since verifier 4.0.0 an expired verification returns 404 instead of the object.
        try {
            $result = $this->client->getVerification($verification->verifier_id);
        } catch (SwissEidException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }

            $verification->update([...$attributes, 'state' => VerificationState::Expired]);
            event(new VerificationExpired($verification->refresh()));

            return $verification;
        }

        $rawState = strtoupper((string) ($result['state'] ?? ''));
        $newState = match ($rawState) {
            'SUCCESS' => VerificationState::Success,
            'FAILED' => VerificationState::Failed,
            default => null,
        };

        if ($newState === null) {
            if ($rawState !== 'PENDING') {
                Log::warning('swiss-eid verifier returned an unknown state', [
                    'verification_id' => $verification->verifier_id,
                    'state' => $rawState,
                ]);
            }

            return $verification;
        }

        $verification->update([
            ...$attributes,
            'state' => $newState,
            'credential_data' => $result['wallet_response']['credential_subject_data'] ?? null,
            'error_code' => $result['wallet_response']['error_code'] ?? null,
            'error_description' => $result['wallet_response']['error_description'] ?? null,
        ]);

        $verification->refresh();

        event($newState === VerificationState::Success
            ? new VerificationCompleted($verification)
            : new VerificationFailed($verification));

        return $verification;
    }
}
