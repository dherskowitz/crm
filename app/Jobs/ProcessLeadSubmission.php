<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\FormType;
use App\Enums\Source;
use App\Enums\Stage;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadFormSubmission;
use App\Models\User;
use App\Notifications\NewLeadInQueue;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessLeadSubmission implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $submissionId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $submission = LeadFormSubmission::find($this->submissionId);

        if (! $submission || $submission->lead_id !== null) {
            return;
        }

        $email = Str::lower(trim((string) ($this->payload['email'] ?? '')));
        $occurredAt = isset($this->payload['submitted_at'])
            ? Carbon::parse($this->payload['submitted_at'])
            : now();

        [$lead, $isNew] = DB::transaction(function () use ($email, $occurredAt) {
            $company = $this->resolveCompany();

            // Dedupe: append to an existing non-terminal lead for this email.
            $existing = Lead::query()
                ->where('email', $email)
                ->nonTerminal()
                ->first();

            if ($existing) {
                $existing->forceFill(['last_activity_at' => now()])->save();
                $lead = $existing;
                $isNew = false;
            } else {
                $lead = Lead::create([
                    'company_id' => $company?->id,
                    'name' => $this->payload['name'] ?? $email,
                    'email' => $email,
                    'phone' => $this->payload['phone'] ?? null,
                    'source' => Source::Website,
                    'form_type' => $this->payload['form_type'] ?? FormType::Other->value,
                    'stage' => Stage::New,
                    'owner_id' => null,
                    'last_activity_at' => now(),
                    ...$this->attribution(),
                ]);
                $isNew = true;
            }

            $this->recordSubmissionActivity($lead, $occurredAt);
            $this->storeAttachments($lead);

            return [$lead, $isNew];
        });

        $submission->update(['lead_id' => $lead->id]);

        if ($isNew) {
            Notification::send(User::all(), new NewLeadInQueue($lead));
        }
    }

    private function resolveCompany(): ?Company
    {
        $company = $this->payload['company'] ?? null;

        if (! is_array($company) || blank($company['name'] ?? null)) {
            return null;
        }

        $domain = $company['domain'] ?? null;

        if (filled($domain)) {
            return Company::firstOrCreate(['domain' => $domain], ['name' => $company['name']]);
        }

        return Company::firstOrCreate(['name' => $company['name']]);
    }

    /**
     * @return array<string, string|null>
     */
    private function attribution(): array
    {
        $attribution = $this->payload['attribution'] ?? [];

        return [
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
            'referrer' => $attribution['referrer'] ?? null,
            'landing_page' => $attribution['landing_page'] ?? null,
        ];
    }

    private function recordSubmissionActivity(Lead $lead, CarbonInterface $occurredAt): void
    {
        Activity::log($lead, ActivityType::FormSubmission, [
            'message' => $this->payload['message'] ?? null,
            'form_type' => $this->payload['form_type'] ?? null,
        ], occurredAt: $occurredAt);
    }

    private function storeAttachments(Lead $lead): void
    {
        $disk = config('crm.attachments.disk');

        $maxSize = (int) config('crm.attachments.max_size');

        foreach ($this->payload['attachments'] ?? [] as $attachment) {
            $contents = base64_decode((string) $attachment['data_base64'], true);

            // Reject undecodable or over-size blobs — the declared `size` is
            // attacker-controlled metadata, so enforce against the real bytes.
            if ($contents === false || strlen($contents) > $maxSize) {
                continue;
            }

            // Never trust the supplied filename for the storage path.
            $filename = basename((string) $attachment['filename']);
            $path = 'attachments/'.Str::uuid().'/'.$filename;
            Storage::disk($disk)->put($path, $contents);

            $lead->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'filename' => $filename,
                'size' => strlen($contents),
                'mime' => $attachment['mime'],
                'uploaded_by' => null,
            ]);
        }
    }
}
