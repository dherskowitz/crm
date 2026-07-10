<?php

namespace App\Actions\Leads;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MergeLeads
{
    /**
     * Soft-merge the loser lead into the survivor: re-point all child records
     * (activities, tasks, attachments, tag pivots) onto the survivor, backfill
     * any null survivor fields from the loser (survivor wins on conflict),
     * point the loser at the survivor via merged_into_id, and log a merge
     * activity. The loser row is kept so the merge stays reversible; the
     * NotMergedScope global scope hides it from normal queries.
     */
    public function __invoke(Lead $survivor, Lead $loser, User $actor): Lead
    {
        return DB::transaction(function () use ($survivor, $loser, $actor) {
            // Capture what this merge moves/changes before it happens, so it can
            // be reversed exactly (see UnmergeLeads).
            $activityIds = Activity::where('lead_id', $loser->id)->pluck('id')->all();
            $taskIds = Task::where('lead_id', $loser->id)->pluck('id')->all();
            $attachmentIds = Attachment::where('attachable_type', $loser->getMorphClass())
                ->where('attachable_id', $loser->id)
                ->pluck('id')->all();
            $addedTagIds = array_values(array_diff(
                $loser->tags()->pluck('tags.id')->all(),
                $survivor->tags()->pluck('tags.id')->all(),
            ));
            $backfill = $this->backfillNullFields($survivor, $loser);

            Activity::whereIn('id', $activityIds)->update(['lead_id' => $survivor->id]);
            Task::whereIn('id', $taskIds)->update(['lead_id' => $survivor->id]);
            Attachment::whereIn('id', $attachmentIds)->update(['attachable_id' => $survivor->id]);

            $survivor->tags()->syncWithoutDetaching($addedTagIds);
            $survivor->forceFill($backfill)->save();

            $mergeActivity = Activity::log($survivor, ActivityType::Merge, [
                'merged_lead_id' => $loser->id,
                'merged_lead_name' => $loser->name,
                'survivor_name' => $survivor->name,
            ], $actor);

            $loser->forceFill([
                'merged_into_id' => $survivor->id,
                'merge_snapshot' => [
                    'activity_ids' => $activityIds,
                    'task_ids' => $taskIds,
                    'attachment_ids' => $attachmentIds,
                    'added_tag_ids' => $addedTagIds,
                    'backfilled_fields' => array_keys($backfill),
                    'merge_activity_id' => $mergeActivity->id,
                ],
            ])->save();

            return $survivor;
        });
    }

    /**
     * Build the attribute map of survivor fields that are null and should be
     * filled from the loser; the survivor always wins where it already holds a
     * value, so only its null fields are returned.
     *
     * @return array<string, mixed>
     */
    private function backfillNullFields(Lead $survivor, Lead $loser): array
    {
        $fields = [
            'company_id', 'email', 'phone', 'value', 'expected_close_date', 'owner_id',
            'form_type', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
            'utm_content', 'referrer', 'landing_page', 'first_contacted_at', 'converted_at',
        ];

        $backfill = [];

        foreach ($fields as $field) {
            if ($survivor->getAttribute($field) === null && $loser->getAttribute($field) !== null) {
                $backfill[$field] = $loser->getAttribute($field);
            }
        }

        return $backfill;
    }
}
