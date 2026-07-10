<?php

namespace App\Actions\Leads;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Lead;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class UnmergeLeads
{
    /**
     * Reverse a lead merge using the snapshot stored on the loser: move its
     * child records back off the survivor, detach the tags the merge added,
     * null the fields it backfilled, delete the merge activity, and un-hide the
     * loser by clearing merged_into_id.
     */
    public function __invoke(Lead $loser): Lead
    {
        return DB::transaction(function () use ($loser) {
            /** @var array<string, mixed> $snapshot */
            $snapshot = $loser->merge_snapshot ?? [];
            $survivor = Lead::withoutGlobalScopes()->findOrFail($loser->merged_into_id);

            Activity::whereIn('id', $snapshot['activity_ids'] ?? [])->update(['lead_id' => $loser->id]);
            Task::whereIn('id', $snapshot['task_ids'] ?? [])->update(['lead_id' => $loser->id]);
            Attachment::whereIn('id', $snapshot['attachment_ids'] ?? [])->update(['attachable_id' => $loser->id]);

            if (! empty($snapshot['added_tag_ids'])) {
                $survivor->tags()->detach($snapshot['added_tag_ids']);
            }

            foreach ($snapshot['backfilled_fields'] ?? [] as $field) {
                $survivor->setAttribute($field, null);
            }

            $survivor->save();

            if (isset($snapshot['merge_activity_id'])) {
                Activity::where('id', $snapshot['merge_activity_id'])->delete();
            }

            $loser->forceFill(['merged_into_id' => null, 'merge_snapshot' => null])->save();

            return $loser;
        });
    }
}
