<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity as AuditActivity;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasCapability('view_audit_log'), 403);

        $entries = AuditActivity::query()
            ->with(['causer', 'subject'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AuditActivity $activity) => $this->present($activity));

        return Inertia::render('audit/index', [
            'entries' => $entries,
        ]);
    }

    /**
     * Map a Spatie audit record to a serializable shape for the admin table.
     *
     * @return array<string, mixed>
     */
    private function present(AuditActivity $activity): array
    {
        /** @var array<string, mixed> $changes */
        $changes = $activity->attribute_changes?->toArray() ?? [];

        return [
            'id' => $activity->id,
            'description' => $activity->description,
            'log_name' => $activity->log_name,
            'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'causer' => $activity->causer?->getAttribute('name'),
            'changes' => [
                'attributes' => $changes['attributes'] ?? null,
                'old' => $changes['old'] ?? null,
            ],
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
