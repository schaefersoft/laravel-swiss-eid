<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Events\VerificationExpired;
use SwissEid\LaravelSwissEid\Models\EidVerification;

class VerificationStatusController extends Controller
{
    /**
     * Return the current state of a verification (for client-side polling).
     *
     * GET /swiss-eid/status/{verification}
     */
    public function __invoke(string $verification): JsonResponse
    {
        /** @var EidVerification $record */
        $record = EidVerification::findOrFail($verification);

        // If still pending but the TTL has passed, mark it expired
        if ($record->isPending() && $record->isExpired()) {
            $record->update(['state' => VerificationState::Expired]);
            $record->refresh();

            event(new VerificationExpired($record));
        }

        return response()->json([
            'state' => $record->state->value,
            'label' => $record->state->label(),
            'is_terminal' => $record->state->isTerminal(),
        ]);
    }
}
