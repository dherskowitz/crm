<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('tags can be synced onto a visible lead', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();
    $tags = Tag::factory()->count(2)->create();

    $this->actingAs($user)
        ->post(route('leads.tags', $lead), ['tag_ids' => $tags->pluck('id')->all()])
        ->assertRedirect();

    expect($lead->tags()->count())->toBe(2);

    // Syncing with a subset removes the others.
    $this->actingAs($user)
        ->post(route('leads.tags', $lead), ['tag_ids' => [$tags->first()->id]])
        ->assertRedirect();
    expect($lead->tags()->count())->toBe(1);
});

test('a member cannot tag a lead they cannot see', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy(User::factory()->member()->create())->create();
    $tag = Tag::factory()->create();

    $this->actingAs($user)
        ->post(route('leads.tags', $lead), ['tag_ids' => [$tag->id]])
        ->assertForbidden();
});

test('a note can be pinned and unpinned by its author', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();
    $note = Activity::factory()->for($lead)->create([
        'user_id' => $user->id,
        'type' => ActivityType::Note,
    ]);

    $this->actingAs($user)
        ->post(route('activities.pin', $note), ['pinned' => true])
        ->assertRedirect();
    expect($note->refresh()->is_pinned)->toBeTrue();

    $this->actingAs($user)
        ->post(route('activities.pin', $note), ['pinned' => false])
        ->assertRedirect();
    expect($note->refresh()->is_pinned)->toBeFalse();
});

test('a member cannot pin someone elses note', function () {
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();
    $note = Activity::factory()->for($lead)->create([
        'user_id' => User::factory()->create()->id,
        'type' => ActivityType::Note,
    ]);

    $this->actingAs($user)
        ->post(route('activities.pin', $note), ['pinned' => true])
        ->assertForbidden();
});

test('write access to a note does not outlive read access to its lead', function () {
    $member = User::factory()->member()->create();
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->ownedBy($member)->create();
    $note = Activity::factory()->for($lead)->create([
        'user_id' => $member->id,
        'type' => ActivityType::Note,
    ]);

    // Reassign the lead away from the member, who now cannot see it.
    $lead->forceFill(['owner_id' => $manager->id])->save();

    $this->actingAs($member)
        ->post(route('activities.pin', $note), ['pinned' => true])
        ->assertForbidden();
    $this->actingAs($member)
        ->delete(route('activities.destroy', $note))
        ->assertForbidden();
});

test('an allowed file can be uploaded and a disallowed one is rejected', function () {
    Storage::fake('local');
    $user = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy($user)->create();

    $this->actingAs($user)
        ->post(route('attachments.store', $lead), [
            'file' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($lead->attachments()->count())->toBe(1);
    Storage::disk('local')->assertExists($lead->attachments()->first()->path);

    $this->actingAs($user)
        ->post(route('attachments.store', $lead), [
            'file' => UploadedFile::fake()->create('evil.exe', 100, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('file');
});

test('only the uploader or an admin can delete an attachment', function () {
    Storage::fake('local');
    $uploader = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    $lead = Lead::factory()->unassigned()->create();
    $attachment = Attachment::factory()->create([
        'attachable_id' => $lead->id,
        'uploaded_by' => $uploader->id,
    ]);

    $this->actingAs($other)->delete(route('attachments.destroy', $attachment))->assertForbidden();
    $this->actingAs($uploader)->delete(route('attachments.destroy', $attachment))->assertRedirect();
    $this->assertModelMissing($attachment);
});
