<?php

namespace App\Console\Commands;

use App\Actions\Automation\ApplyAutomationRule;
use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Models\AutomationRule;
use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:run-idle-automations')]
#[Description('Apply time-based (lead idle) automation rules')]
class RunIdleAutomations extends Command
{
    public function handle(ApplyAutomationRule $apply): int
    {
        $rules = AutomationRule::query()
            ->activeFor(AutomationTrigger::LeadIdle)
            ->get();

        foreach ($rules as $rule) {
            $days = (int) ($rule->conditions['days'] ?? 7);

            Lead::query()
                ->active()
                ->whereNull('converted_at')
                ->whereNotIn('stage', ['won', 'lost'])
                ->where('last_activity_at', '<=', now()->subDays($days))
                ->chunkById(200, function ($leads) use ($apply, $rule) {
                    foreach ($leads as $lead) {
                        // Avoid piling up duplicate tasks on every daily run.
                        if (
                            $rule->action === AutomationAction::CreateTask
                            && $lead->tasks()->whereNull('completed_at')
                                ->where('title', $rule->params['title'] ?? $rule->name)
                                ->exists()
                        ) {
                            continue;
                        }

                        $apply($rule, $lead);
                    }
                });
        }

        return self::SUCCESS;
    }
}
