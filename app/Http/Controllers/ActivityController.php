<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Requests\Activities\StoreActivityRequest;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\MentionedInNote;
use App\Support\TiptapMentions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class ActivityController extends Controller
{
    public function store(StoreActivityRequest $request, Lead $lead): RedirectResponse
    {
        $occurredAt = $request->filled('occurred_at')
            ? Carbon::parse($request->string('occurred_at')->toString())
            : now();

        $activity = $lead->activities()->create([
            'user_id' => $request->user()->id,
            'type' => $request->validated('type'),
            'body' => $request->validated('body'),
            'occurred_at' => $occurredAt,
        ]);

        $this->syncMentions($activity, $request->user());

        // First human engagement drives speed-to-lead.
        $lead->forceFill([
            'last_activity_at' => now(),
            'first_contacted_at' => $lead->first_contacted_at ?? now(),
        ])->save();

        return back()->with('status', 'activity-logged');
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity);

        $validated = $request->validate([
            'body' => ['required', 'array'],
        ]);

        $activity->update(['body' => $validated['body']]);
        $this->syncMentions($activity, $request->user());

        return back()->with('status', 'activity-updated');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        // System-generated entries (stage changes, submissions, merges) are part
        // of the permanent record and cannot be hand-deleted.
        abort_if(in_array($activity->type, [
            ActivityType::StageChange,
            ActivityType::FormSubmission,
            ActivityType::Merge,
            ActivityType::System,
        ], true), 403);

        $activity->delete();

        return back()->with('status', 'activity-deleted');
    }

    /**
     * Pin or unpin a note so it surfaces at the top of the timeline.
     */
    public function pin(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity);

        $activity->update(['is_pinned' => $request->boolean('pinned')]);

        return back()->with('status', 'activity-pinned');
    }

    /**
     * Sync the @mention pivot from the note's structured content and notify
     * newly-mentioned users (never the author).
     */
    private function syncMentions(Activity $activity, User $author): void
    {
        if ($activity->type !== ActivityType::Note) {
            return;
        }

        $mentionedIds = TiptapMentions::extractUserIds($activity->body);
        $existing = $activity->mentionedUsers()->pluck('users.id')->all();
        $activity->mentionedUsers()->sync($mentionedIds);

        $newlyMentioned = User::query()
            ->whereIn('id', array_diff($mentionedIds, $existing))
            ->whereKeyNot($author->id)
            ->get();

        Notification::send($newlyMentioned, new MentionedInNote($activity, $author));
    }
}
