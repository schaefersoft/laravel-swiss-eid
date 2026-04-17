<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Events\VerificationCompleted;
use SwissEid\LaravelSwissEid\Events\VerificationFailed;
use SwissEid\LaravelSwissEid\Models\EidVerification;
use SwissEid\LaravelSwissEid\VerifierClient;

class WebhookController extends Controller
{
    public function __construct(
        private readonly VerifierClient $client,
    ) {}

    /**
     * Handle an incoming webhook from the swiyu verifier.
     *
     * The verifier POSTs a payload containing the `verification_id` when a
     * wallet responds to a presentation request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        \Log::info('swiss-eid webhook payload', ['body' => $request->all(), 'raw' => $request->getContent()]);

        $verificationId = (string) $request->input('verification_id', '');

        /** @var EidVerification $verification */
        $verification = EidVerification::where('verifier_id', $verificationId)->firstOrFail();

        // Fetch the full result from the verifier
        $result = $this->client->getVerification($verificationId);

        $rawState = strtoupper((string) ($result['state'] ?? ''));
        $newState = $rawState === 'SUCCESS' ? VerificationState::Success : VerificationState::Failed;
        $credentialData = $result['wallet_response']['credential_subject_data'] ?? null;

        $verification->update([
            'state' => $newState,
            'credential_data' => $credentialData,
            'webhook_received_at' => now(),
        ]);

        // Reload to get cast values applied
        $verification->refresh();

        match ($verification->state) {
            VerificationState::Success => event(new VerificationCompleted($verification)),
            VerificationState::Failed => event(new VerificationFailed($verification)),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }
}
