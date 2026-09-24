<?php

declare(strict_types=1);

namespace SchaeferSoft\SwissEid\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SchaeferSoft\SwissEid\Models\EidVerification;
use SchaeferSoft\SwissEid\VerificationSynchronizer;

class WebhookController extends Controller
{
    public function __construct(
        private readonly VerificationSynchronizer $synchronizer,
    ) {}

    /**
     * Handle an incoming webhook from the swiyu verifier.
     *
     * The verifier POSTs a payload containing the `verification_id` when a
     * wallet responds to a presentation request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $verificationId = (string) $request->input('verification_id', '');

        $verification = EidVerification::where('verifier_id', $verificationId)->first();

        // Unknown verification: acknowledge with 200 so the verifier stops
        // retrying the callback — there is nothing for us to update.
        if ($verification === null) {
            \Log::warning('swiss-eid webhook for unknown verification_id', [
                'verification_id' => $verificationId,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        // Idempotency: the verifier delivers callbacks at-least-once and keeps
        // retrying until it receives a 2xx response. Once we have reached a
        // terminal state we acknowledge without re-processing or re-firing events.
        if ($verification->state->isTerminal()) {
            return response()->json(['status' => 'ok']);
        }

        $verification = $this->synchronizer->sync($verification, [
            'webhook_received_at' => now(),
        ]);

        return response()->json([
            'status' => $verification->state->isTerminal() ? 'ok' : 'ignored',
        ]);
    }
}
