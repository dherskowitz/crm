<?php

namespace App\Listeners;

use App\Actions\Automation\ApplyAutomationRule;
use App\Enums\AutomationTrigger;
use App\Events\LeadStageChanged;
use App\Models\AutomationRule;

class RunStageChangeAutomations
{
    public function __construct(private ApplyAutomationRule $apply) {}

    public function handle(LeadStageChanged $event): void
    {
        AutomationRule::query()
            ->activeFor(AutomationTrigger::StageChange)
            ->get()
            ->each(function (AutomationRule $rule) use ($event) {
                $to = $rule->conditions['to'] ?? null;

                // A rule with no `to` condition fires on any stage change.
                if ($to === null || $to === $event->to->value) {
                    ($this->apply)($rule, $event->lead);
                }
            });
    }
}
