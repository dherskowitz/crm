<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;

test('logging a note records an activity and stamps first_contacted_at', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create(['first_contacted_at' => null]);

    $this->actingAs($user)->post(route('activities.store', $lead), [
        'type' => 'note',
        'body' => ['type' => 'doc', 'content' => []],
    ])->assertRedirect();

    expect($lead->activities()->where('type', ActivityType::Note->value)->count())->toBe(1)
        ->and($lead->refresh()->first_contacted_at)->not->toBeNull();
});

test('a user cannot log activity on a lead they cannot see', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy(User::factory()->member()->create())->create();

    $this->actingAs($user)->post(route('activities.store', $lead), [
        'type' => 'call',
        'body' => ['text' => 'rang'],
    ])->assertForbidden();
});

test('a user can delete their own note but not a system entry', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();
    $note = Activity::factory()->for($lead)->create(['user_id' => $user->id, 'type' => ActivityType::Note]);
    $system = Activity::factory()->for($lead)->system()->create();

    $this->actingAs($user)->delete(route('activities.destroy', $note))->assertRedirect();
    $this->assertModelMissing($note);

    $this->actingAs($user)->delete(route('activities.destroy', $system))->assertForbidden();
});

test('a user cannot delete someone elses note', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();
    $note = Activity::factory()->for($lead)->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user)->delete(route('activities.destroy', $note))->assertForbidden();
});

test('a task can be created and completed', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();

    $this->actingAs($user)->post(route('tasks.store', $lead), [
        'title' => 'Follow up Friday',
    ])->assertRedirect();

    $task = Task::first();
    expect($task->owner_id)->toBe($user->id);

    $this->actingAs($user)->patch(route('tasks.update', $task), ['completed' => true])
        ->assertRedirect();
    expect($task->refresh()->completed_at)->not->toBeNull();
});

test('a member cannot modify another members task but a manager can', function () {
    $owner = User::factory()->member()->create();
    $member = User::factory()->member()->create();
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->unassigned()->create();
    $task = Task::factory()->for($lead)->create(['owner_id' => $owner->id]);

    $this->actingAs($member)->patch(route('tasks.update', $task), ['completed' => true])
        ->assertForbidden();

    $this->actingAs($manager)->patch(route('tasks.update', $task), ['completed' => true])
        ->assertRedirect();
});
