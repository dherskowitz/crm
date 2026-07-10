<?php

namespace App\Jobs;

use App\Actions\Leads\ChangeLeadStage;
use App\Enums\ActivityType;
use App\Enums\Stage;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBulkLeadAction implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int $userId,
        public array $ids,
        public string $action,
        public array $params = [],
    ) {}

    public function handle(ChangeLeadStage $changeStage): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        Lead::whereIn('id', $this->ids)->get()->each(function (Lead $lead) use ($user, $changeStage) {
            // Each record runs through the same policy as a single-record action,
            // so a bulk action only ever touches permitted records.
            match ($this->action) {
                'reassign' => $this->reassign($user, $lead),
                'tag' => $this->tag($user, $lead),
                'stage' => $this->stage($user, $lead, $changeStage),
                'delete' => $this->delete($user, $lead),
                default => null,
            };
        });
    }

    private function reassign(User $user, Lead $lead): void
    {
        if (! $user->can('reassign', $lead)) {
            return;
        }

        $lead->forceFill(['owner_id' => $this->params['owner_id'] ?? null])->save();
        $this->log($lead, $user, 'reassigned');
    }

    private function tag(User $user, Lead $lead): void
    {
        if (! $user->can('update', $lead)) {
            return;
        }

        $lead->tags()->syncWithoutDetaching([$this->params['tag_id']]);
    }

    private function stage(User $user, Lead $lead, ChangeLeadStage $changeStage): void
    {
        if (! $user->can('update', $lead)) {
            return;
        }

        $stage = Stage::from($this->params['stage']);

        $lead->forceFill([
            'lost_reason' => $stage === Stage::Lost ? ($this->params['lost_reason'] ?? null) : null,
            'win_reason' => $stage === Stage::Won ? ($this->params['win_reason'] ?? null) : null,
        ])->save();

        $changeStage($lead, $stage, $user);
    }

    private function delete(User $user, Lead $lead): void
    {
        if ($user->can('delete', $lead)) {
            $lead->delete();
        }
    }

    private function log(Lead $lead, User $user, string $event): void
    {
        Activity::log($lead, ActivityType::System, ['event' => $event], $user);
    }
}
