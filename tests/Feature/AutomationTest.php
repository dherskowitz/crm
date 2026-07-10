<?php

use App\Enums\Stage;
use App\Models\AutomationRule;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\AutomationAlert;
use Illuminate\Support\Facades\Notification;

test('a stage-change rule creates a task when the condition matches', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($admin)->stage(Stage::Contacted)->create();

    AutomationRule::create([
        'name' => 'Qualify follow-up',
        'trigger' => 'stage_change',
        'conditions' => ['to' => 'qualified'],
        'action' => 'create_task',
        'params' => ['title' => 'Send proposal', 'due_in_days' => 2],
        'is_active' => true,
    ]);

    // Non-matching transition does nothing.
    $this->actingAs($admin)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Qualified->value,
    ])->assertRedirect();

    expect($lead->tasks()->where('title', 'Send proposal')->count())->toBe(1);
});

test('a stage-change rule is skipped when inactive or condition mismatches', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($admin)->stage(Stage::New)->create();

    AutomationRule::create([
        'name' => 'Won celebration',
        'trigger' => 'stage_change',
        'conditions' => ['to' => 'won'],
        'action' => 'create_task',
        'params' => ['title' => 'Celebrate'],
        'is_active' => false,
    ]);

    $this->actingAs($admin)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Contacted->value,
    ])->assertRedirect();

    expect($lead->tasks()->count())->toBe(0);
});

test('the idle command applies lead-idle rules and dedupes tasks', function () {
    $owner = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($owner)->stage(Stage::Contacted)->create([
        'last_activity_at' => now()->subDays(10),
    ]);

    AutomationRule::create([
        'name' => 'Nudge idle',
        'trigger' => 'lead_idle',
        'conditions' => ['days' => 7],
        'action' => 'create_task',
        'params' => ['title' => 'Re-engage'],
        'is_active' => true,
    ]);

    $this->artisan('app:run-idle-automations')->assertSuccessful();
    $this->artisan('app:run-idle-automations')->assertSuccessful();

    // Deduped: only one open task despite two runs.
    expect($lead->tasks()->where('title', 'Re-engage')->count())->toBe(1);
});

test('a lead-idle notification rule alerts the owner', function () {
    Notification::fake();
    $owner = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($owner)->stage(Stage::Contacted)->create([
        'last_activity_at' => now()->subDays(20),
    ]);

    AutomationRule::create([
        'name' => 'Stale alert',
        'trigger' => 'lead_idle',
        'conditions' => ['days' => 14],
        'action' => 'send_notification',
        'params' => ['message' => 'This lead has gone cold'],
        'is_active' => true,
    ]);

    $this->artisan('app:run-idle-automations')->assertSuccessful();

    Notification::assertSentTo($owner, AutomationAlert::class);
});

test('only admins can manage automation rules', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('automations.index'))->assertForbidden();

    $this->actingAs(User::factory()->member()->create())
        ->post(route('automations.store'), [
            'name' => 'x', 'trigger' => 'stage_change', 'action' => 'create_task',
        ])->assertForbidden();
});

test('an admin can edit an existing rule through the update endpoint', function () {
    $admin = User::factory()->admin()->create();
    $rule = AutomationRule::create([
        'name' => 'Old name',
        'trigger' => 'stage_change',
        'conditions' => ['to' => 'qualified'],
        'action' => 'create_task',
        'params' => ['title' => 'Old task', 'due_in_days' => 2],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('automations.update', $rule), [
            'name' => 'New name',
            'trigger' => 'lead_idle',
            'conditions' => ['days' => 5],
            'action' => 'send_notification',
            'params' => ['message' => 'Reach out'],
            'is_active' => true,
        ])
        ->assertRedirect();

    $rule->refresh();
    expect($rule->name)->toBe('New name')
        ->and($rule->trigger->value)->toBe('lead_idle')
        ->and($rule->conditions)->toBe(['days' => 5])
        ->and($rule->action->value)->toBe('send_notification')
        ->and($rule->params)->toBe(['message' => 'Reach out']);
});
