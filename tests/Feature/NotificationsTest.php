<?php

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Notifications\LeadClaimed;
use App\Notifications\MentionedInNote;
use App\Notifications\NewLeadInQueue;
use App\Notifications\TaskDue;
use Illuminate\Support\Facades\Notification;

test('claiming a queued lead notifies the rest of the team', function () {
    Notification::fake();
    $claimer = User::factory()->member()->create();
    $teammate = User::factory()->member()->create();
    $lead = Lead::factory()->unassigned()->create();

    $this->actingAs($claimer)->post(route('leads.claim', $lead))->assertRedirect();

    Notification::assertSentTo($teammate, LeadClaimed::class);
    Notification::assertNotSentTo($claimer, LeadClaimed::class);
});

test('mentioning a user in a note notifies them and records the pivot', function () {
    Notification::fake();
    $author = User::factory()->admin()->create();
    $mentioned = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($author)->create();

    $body = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'mention',
                'attrs' => ['id' => $mentioned->id, 'label' => $mentioned->name],
            ]],
        ]],
    ];

    $this->actingAs($author)
        ->post(route('activities.store', $lead), ['type' => 'note', 'body' => $body])
        ->assertRedirect();

    Notification::assertSentTo($mentioned, MentionedInNote::class);
    Notification::assertNotSentTo($author, MentionedInNote::class);

    $activity = Activity::where('lead_id', $lead->id)->latest('id')->first();
    expect($activity->mentionedUsers()->pluck('users.id')->all())->toContain($mentioned->id);
});

test('the due-tasks command notifies the owner once and stamps it', function () {
    Notification::fake();
    $owner = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($owner)->create();
    $task = Task::factory()->for($lead)->overdue()->create(['owner_id' => $owner->id]);

    $this->artisan('app:notify-due-tasks')->assertSuccessful();
    Notification::assertSentToTimes($owner, TaskDue::class, 1);
    expect($task->refresh()->due_notified_at)->not->toBeNull();

    // Running again does not re-notify.
    $this->artisan('app:notify-due-tasks')->assertSuccessful();
    Notification::assertSentToTimes($owner, TaskDue::class, 1);
});

test('a new website lead notifies the team via the database channel', function () {
    config(['crm.webhook.secret' => 'test-secret']);
    Notification::fake();
    User::factory()->count(2)->create();

    $payload = [
        'submission_id' => (string) Str::uuid(),
        'form_type' => 'contact',
        'email' => 'fresh@acme.com',
        'name' => 'Fresh Lead',
    ];
    $body = json_encode($payload);
    $ts = (string) time();

    $this->call('POST', '/api/webhooks/leads', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CRM_TIMESTAMP' => $ts,
        'HTTP_X_CRM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$body, 'test-secret'),
    ], $body)->assertStatus(202);

    Notification::assertSentTo(User::all(), NewLeadInQueue::class);
});
