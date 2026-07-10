<?php

namespace App\Actions\Automation;

use App\Enums\AutomationAction;
use App\Enums\Role;
use App\Models\AutomationRule;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\AutomationAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class ApplyAutomationRule
{
    /**
     * Execute a single rule's action against a lead.
     */
    public function __invoke(AutomationRule $rule, Lead $lead): void
    {
        match ($rule->action) {
            AutomationAction::CreateTask => $this->createTask($rule, $lead),
            AutomationAction::SendNotification => $this->sendNotification($rule, $lead),
        };
    }

    private function createTask(AutomationRule $rule, Lead $lead): void
    {
        $ownerId = $lead->owner_id ?? $this->admins()->value('id');

        if ($ownerId === null) {
            return;
        }

        $dueInDays = (int) ($rule->params['due_in_days'] ?? 3);

        $lead->tasks()->create([
            'owner_id' => $ownerId,
            'title' => $rule->params['title'] ?? $rule->name,
            'due_at' => now()->addDays($dueInDays),
        ]);
    }

    private function sendNotification(AutomationRule $rule, Lead $lead): void
    {
        $message = $rule->params['message'] ?? $rule->name;

        $recipients = $lead->owner_id
            ? User::whereKey($lead->owner_id)->get()
            : $this->admins()->get();

        Notification::send($recipients, new AutomationAlert($lead, $message));
    }

    /**
     * Query for the admin users used as the fallback recipient/owner.
     *
     * @return Builder<User>
     */
    private function admins(): Builder
    {
        return User::where('role', Role::Admin->value);
    }
}
