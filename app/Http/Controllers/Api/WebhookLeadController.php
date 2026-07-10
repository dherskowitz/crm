<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\LeadWebhookRequest;
use App\Jobs\ProcessLeadSubmission;
use App\Models\LeadFormSubmission;
use Illuminate\Http\JsonResponse;

class WebhookLeadController extends Controller
{
    /**
     * Accept a signed website form submission, dedupe by submission_id, and
     * queue processing. Acknowledges quickly with 202.
     */
    public function __invoke(LeadWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $submission = LeadFormSubmission::firstOrCreate([
            'submission_id' => $payload['submission_id'],
        ]);

        // A repeat submission_id is a no-op so webhook retries never double-create.
        if ($submission->wasRecentlyCreated) {
            ProcessLeadSubmission::dispatch($submission->id, $payload);
        }

        return response()->json(['status' => 'accepted'], 202);
    }
}
