<?php

namespace App\Http\Controllers;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Enums\Stage;
use App\Http\Requests\Automation\AutomationRuleRequest;
use App\Models\AutomationRule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('automations/index', [
            'rules' => AutomationRule::latest()->get()->map(fn (AutomationRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'trigger' => $rule->trigger->value,
                'trigger_label' => $rule->trigger->label(),
                'action' => $rule->action->value,
                'action_label' => $rule->action->label(),
                'conditions' => $rule->conditions,
                'params' => $rule->params,
                'is_active' => $rule->is_active,
            ]),
            'triggers' => AutomationTrigger::options(),
            'actions' => AutomationAction::options(),
            'stages' => Stage::options(),
        ]);
    }

    public function store(AutomationRuleRequest $request): RedirectResponse
    {
        AutomationRule::create($request->validated());

        return back()->with('status', 'rule-created');
    }

    public function update(AutomationRuleRequest $request, AutomationRule $automationRule): RedirectResponse
    {
        $automationRule->update($request->validated());

        return back()->with('status', 'rule-updated');
    }

    public function destroy(AutomationRule $automationRule): RedirectResponse
    {
        $automationRule->delete();

        return back()->with('status', 'rule-deleted');
    }
}
