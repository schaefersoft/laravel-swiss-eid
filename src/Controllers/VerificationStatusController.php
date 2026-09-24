<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SchaeferSoft\SwissEid\Enums\VerificationState;
use SchaeferSoft\SwissEid\Events\VerificationExpired;
use SchaeferSoft\SwissEid\Models\EidVerification;

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
