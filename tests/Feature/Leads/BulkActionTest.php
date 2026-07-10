<?php

use App\Enums\Stage;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;

test('an admin can bulk delete leads but a member cannot', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $leads = Lead::factory()->count(3)->unassigned()->create();
    $ids = $leads->pluck('id')->all();

    // Member lacks delete capability — nothing is removed.
    $this->actingAs($member)
        ->post(route('leads.bulk'), ['ids' => $ids, 'action' => 'delete'])
        ->assertRedirect();
    expect(Lead::whereIn('id', $ids)->count())->toBe(3);

    $this->actingAs($admin)
        ->post(route('leads.bulk'), ['ids' => $ids, 'action' => 'delete'])
        ->assertRedirect();
    expect(Lead::whereIn('id', $ids)->count())->toBe(0);
});

test('a manager can bulk reassign', function () {
    $manager = User::factory()->manager()->create();
    $target = User::factory()->member()->create();
    $leads = Lead::factory()->count(2)->unassigned()->create();

    $this->actingAs($manager)->post(route('leads.bulk'), [
        'ids' => $leads->pluck('id')->all(),
        'action' => 'reassign',
        'owner_id' => $target->id,
    ])->assertRedirect();

    expect(Lead::where('owner_id', $target->id)->count())->toBe(2);
});

test('bulk stage change to won requires a win reason and stamps converted_at', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->stage(Stage::InProgress)->create(['converted_at' => null]);

    $this->actingAs($admin)->post(route('leads.bulk'), [
        'ids' => [$lead->id],
        'action' => 'stage',
        'stage' => Stage::Won->value,
    ])->assertSessionHasErrors('win_reason');

    $this->actingAs($admin)->post(route('leads.bulk'), [
        'ids' => [$lead->id],
        'action' => 'stage',
        'stage' => Stage::Won->value,
        'win_reason' => 'price',
    ])->assertRedirect();

    expect($lead->refresh()->stage)->toBe(Stage::Won)
        ->and($lead->converted_at)->not->toBeNull();
});

test('a bulk tag applies to every selected lead', function () {
    $manager = User::factory()->manager()->create();
    $tag = Tag::factory()->create();
    $leads = Lead::factory()->count(2)->unassigned()->create();

    $this->actingAs($manager)->post(route('leads.bulk'), [
        'ids' => $leads->pluck('id')->all(),
        'action' => 'tag',
        'tag_id' => $tag->id,
    ])->assertRedirect();

    expect($tag->leads()->count())->toBe(2);
});

test('a member bulk action only touches leads visible to them', function () {
    $member = User::factory()->member()->create();
    $mine = Lead::factory()->ownedBy($member)->stage(Stage::New)->create();
    $theirs = Lead::factory()->ownedBy(User::factory()->member()->create())
        ->stage(Stage::New)->create();

    $this->actingAs($member)->post(route('leads.bulk'), [
        'ids' => [$mine->id, $theirs->id],
        'action' => 'stage',
        'stage' => Stage::Contacted->value,
    ])->assertRedirect();

    expect($mine->refresh()->stage)->toBe(Stage::Contacted)
        ->and($theirs->refresh()->stage)->toBe(Stage::New);
});
