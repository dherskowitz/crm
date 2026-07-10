<?php

namespace App\Http\Controllers;

use App\Actions\Leads\ChangeLeadStage;
use App\Enums\ActivityType;
use App\Enums\Source;
use App\Enums\Stage;
use App\Http\Requests\Leads\ReassignLeadRequest;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\LeadClaimed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);

        $user = $request->user();

        $leads = Lead::query()
            ->visibleTo($user)
            ->active()
            ->with(['company:id,name', 'owner:id,name', 'tags:id,name,color'])
            ->filter($request, $user)
            ->orderByDesc('last_activity_at')
            ->get()
            ->map(fn (Lead $lead) => $this->presentSummary($lead));

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'stages' => Stage::options(),
            'owners' => $this->ownerOptions($user),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'savedViews' => $user->savedViews()->latest()->get(['id', 'name', 'filters']),
            'filters' => $this->activeFilters($request),
        ]);
    }

    /**
     * The active list filters, echoed back to the client. Boolean toggles are
     * cast to real booleans (not the raw "true"/"false" query strings, which
     * are both truthy in JS and would jam the toggle buttons); other filters
     * are passed through only when present.
     *
     * @return array<string, string|bool|int>
     */
    private function activeFilters(Request $request): array
    {
        $filters = $request->only(['stage', 'owner_id', 'search', 'tag_id']);

        foreach (['unassigned', 'mine'] as $toggle) {
            if ($request->has($toggle)) {
                $filters[$toggle] = $request->boolean($toggle);
            }
        }

        return $filters;
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);

        return Inertia::render('leads/show', $this->detailData($request, $lead));
    }

    /**
     * Same payload as show(), as JSON, for the in-list lead detail modal.
     */
    public function detail(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        return response()->json($this->detailData($request, $lead));
    }

    /**
     * The shared lead-detail payload used by both the full page and the modal.
     *
     * @return array<string, mixed>
     */
    private function detailData(Request $request, Lead $lead): array
    {
        $lead->load([
            'company:id,name,domain',
            'owner:id,name',
            'tags:id,name,color',
            'activities.user:id,name',
            'attachments',
            'tasks' => fn ($q) => $q->orderBy('due_at'),
        ]);

        return [
            'lead' => $this->presentDetail($lead),
            'stages' => Stage::options(),
            'owners' => $this->ownerOptions($request->user()),
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'mentionables' => User::orderBy('name')->get(['id', 'name'])->map->only(['id', 'name']),
        ];
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('confirmed');

        $this->guardAgainstDuplicate($request, $data);

        // Only reassigners may pre-assign an owner; everyone else creates into
        // their own/queue context.
        if (! $request->user()->hasCapability('reassign_leads')) {
            unset($data['owner_id']);
        }

        $lead = Lead::create([
            ...$data,
            'source' => Source::Manual,
            'stage' => Stage::New,
            'last_activity_at' => now(),
        ]);

        return redirect()->route('leads.show', $lead)->with('status', 'lead-created');
    }

    public function update(UpdateLeadRequest $request, Lead $lead, ChangeLeadStage $changeStage): RedirectResponse
    {
        $data = $request->validated();
        $newStage = Stage::from($data['stage']);
        $stageChanged = $lead->stage !== $newStage;

        $lead->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'value' => $data['value'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'lost_reason' => $newStage === Stage::Lost ? ($data['lost_reason'] ?? null) : null,
            'win_reason' => $newStage === Stage::Won ? ($data['win_reason'] ?? null) : null,
        ])->save();

        if ($stageChanged) {
            $changeStage($lead, $newStage, $request->user());
        }

        return back()->with('status', 'lead-updated');
    }

    public function claim(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('claim', $lead);

        $claimer = $request->user();

        // Atomic claim: only succeeds if still unassigned, so two simultaneous
        // claims can't both win.
        $claimed = Lead::whereKey($lead->id)->whereNull('owner_id')
            ->update(['owner_id' => $claimer->id]);

        if ($claimed === 0) {
            return back()->with('status', 'lead-already-claimed');
        }

        $lead->setAttribute('owner_id', $claimer->id);

        Activity::log($lead, ActivityType::System, ['event' => 'claimed'], $claimer);

        // Tell the rest of the team so they stop working it.
        Notification::send(
            User::whereKeyNot($claimer->id)->get(),
            new LeadClaimed($lead, $claimer),
        );

        return back()->with('status', 'lead-claimed');
    }

    public function syncTags(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'tag_ids' => ['array'],
            'tag_ids.*' => [Rule::exists('tags', 'id')],
        ]);

        $lead->tags()->sync($validated['tag_ids'] ?? []);

        return back()->with('status', 'tags-updated');
    }

    public function reassign(ReassignLeadRequest $request, Lead $lead): RedirectResponse
    {
        $ownerId = $request->validated('owner_id');
        $lead->forceFill(['owner_id' => $ownerId])->save();

        Activity::log($lead, ActivityType::System, ['event' => 'reassigned', 'owner_id' => $ownerId], $request->user());

        return back()->with('status', 'lead-reassigned');
    }

    /**
     * Reject a manual create that collides with an existing visible lead by
     * email unless the user has explicitly confirmed.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstDuplicate(StoreLeadRequest $request, array $data): void
    {
        if ($request->boolean('confirmed') || blank($data['email'] ?? null)) {
            return;
        }

        $duplicate = Lead::query()
            ->visibleTo($request->user())
            ->where('email', $data['email'])
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'email' => "A lead with this email already exists (#{$duplicate->id}). Submit again to create anyway.",
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSummary(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'stage' => $lead->stage->value,
            'value' => $lead->value,
            'company' => $lead->company?->only(['id', 'name']),
            'owner' => $lead->owner?->only(['id', 'name']),
            'tags' => $lead->relationLoaded('tags')
                ? $lead->tags->map->only(['id', 'name', 'color'])->values()
                : [],
            'last_activity_at' => $lead->last_activity_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(Lead $lead): array
    {
        return [
            ...$this->presentSummary($lead),
            'phone' => $lead->phone,
            'source' => $lead->source->value,
            'form_type' => $lead->form_type?->value,
            'lost_reason' => $lead->lost_reason?->value,
            'win_reason' => $lead->win_reason?->value,
            'expected_close_date' => $lead->expected_close_date?->toDateString(),
            'first_contacted_at' => $lead->first_contacted_at?->toIso8601String(),
            'converted_at' => $lead->converted_at?->toIso8601String(),
            'company_id' => $lead->company_id,
            'tags' => $lead->tags->map->only(['id', 'name', 'color'])->values(),
            'activities' => $lead->activities->map(fn (Activity $a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'body' => $a->body,
                'is_pinned' => $a->is_pinned,
                'user' => $a->user?->only(['id', 'name']),
                'occurred_at' => $a->occurred_at->toIso8601String(),
            ])->values(),
            'tasks' => $lead->tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_at' => $t->due_at?->toIso8601String(),
                'completed_at' => $t->completed_at?->toIso8601String(),
            ])->values(),
            'attachments' => $lead->attachments->map(fn ($a) => [
                'id' => $a->id,
                'filename' => $a->filename,
                'size' => $a->size,
                'mime' => $a->mime,
            ])->values(),
        ];
    }

    /**
     * Assignable owners as {value, label} options, matching the frontend
     * Option type used by the reassign selects.
     *
     * @return list<array{value: int, label: string}>
     */
    private function ownerOptions(User $user): array
    {
        if (! $user->hasCapability('view_all_leads')) {
            return [];
        }

        return array_values(User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])
            ->all());
    }
}
