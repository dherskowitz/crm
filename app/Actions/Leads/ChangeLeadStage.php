<?php

namespace App\Actions\Leads;

use App\Enums\ActivityType;
use App\Enums\Stage;
use App\Events\LeadStageChanged;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeLeadStage
{
    /**
     * Move a lead to a new stage, writing a stage_change activity and keeping
     * the lifecycle timestamps that every metric keys off in sync.
     *
     * Reaching "won" for the first time sets converted_at; the first move out
     * of "new" stamps first_contacted_at (drives speed-to-lead).
     */
    public function __invoke(Lead $lead, Stage $to, User $actor): Lead
    {
        $from = $lead->stage;

        if ($from === $to) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $from, $to, $actor) {
            $lead->stage = $to;

            if ($lead->first_contacted_at === null && $from === Stage::New) {
                $lead->first_contacted_at = now();
            }

            if ($to === Stage::Won && $lead->converted_at === null) {
                $lead->converted_at = now();
            }

            $lead->last_activity_at = now();
            $lead->save();

            Activity::log($lead, ActivityType::StageChange, ['from' => $from->value, 'to' => $to->value], $actor);

            LeadStageChanged::dispatch($lead, $from, $to);

            return $lead;
        });
    }
}
