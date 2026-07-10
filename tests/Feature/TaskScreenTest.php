<?php

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('the tasks screen lists the current user\'s tasks with computed buckets', function () {
    $member = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($member)->create();

    Task::factory()->for($lead)->overdue()->create(['owner_id' => $member->id, 'title' => 'Overdue one']);
    Task::factory()->for($lead)->create([
        'owner_id' => $member->id,
        'title' => 'Future one',
        'due_at' => now()->addDays(5),
    ]);
    Task::factory()->for($lead)->completed()->create(['owner_id' => $member->id, 'title' => 'Done one']);

    $this->actingAs($member)
        ->get(route('tasks.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tasks/index')
            ->where('scope', 'mine')
            ->where('canViewTeam', false)
            ->has('tasks', 3)
            ->where('tasks.0.title', 'Overdue one')
            ->where('tasks.0.group', 'overdue')
        );
});

test('a member only sees their own tasks, even when asking for team scope', function () {
    $member = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    $mine = Lead::factory()->ownedBy($member)->create();
    $theirs = Lead::factory()->ownedBy($other)->create();
    Task::factory()->for($mine)->create(['owner_id' => $member->id]);
    Task::factory()->for($theirs)->create(['owner_id' => $other->id]);

    $this->actingAs($member)
        ->get(route('tasks.index', ['scope' => 'team']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope', 'mine')
            ->has('tasks', 1)
        );
});

test('a manager can view the whole team\'s tasks', function () {
    $manager = User::factory()->manager()->create();
    $rep = User::factory()->member()->create();

    $lead = Lead::factory()->ownedBy($rep)->create();
    Task::factory()->for($lead)->create(['owner_id' => $rep->id]);
    Task::factory()->for($lead)->create(['owner_id' => $manager->id]);

    $this->actingAs($manager)
        ->get(route('tasks.index', ['scope' => 'team']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope', 'team')
            ->where('canViewTeam', true)
            ->has('tasks', 2)
        );
});

test('completing a task from the screen marks it complete', function () {
    $member = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($member)->create();
    $task = Task::factory()->for($lead)->create(['owner_id' => $member->id]);

    $this->actingAs($member)
        ->patch(route('tasks.update', $task), ['completed' => true])
        ->assertRedirect();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

test('the dashboard surfaces the current user\'s open tasks', function () {
    $member = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($member)->create();

    Task::factory()->for($lead)->create(['owner_id' => $member->id, 'title' => 'Open task']);
    Task::factory()->for($lead)->completed()->create(['owner_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('myTasks', 1)
            ->where('myTasks.0.title', 'Open task')
        );
});
