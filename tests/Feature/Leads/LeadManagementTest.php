<?php

use App\Enums\ActivityType;
use App\Enums\Stage;
use App\Models\Lead;
use App\Models\User;

test('the index is scoped to what each role may see', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    Lead::factory()->ownedBy($member)->create();
    Lead::factory()->ownedBy($other)->create();
    Lead::factory()->unassigned()->create();

    $this->actingAs($admin)->get(route('leads.index'))
        ->assertInertia(fn ($page) => $page->component('leads/index')->has('leads', 3));

    $this->actingAs($member)->get(route('leads.index'))
        ->assertInertia(fn ($page) => $page->has('leads', 2)); // own + unassigned
});

test('a member cannot view a lead owned by someone else', function () {
    $member = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy(User::factory()->member()->create())->create();

    $this->actingAs($member)->get(route('leads.show', $lead))->assertForbidden();
});

test('the detail endpoint returns the lead payload as json', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->create(['name' => 'Acme Lead']);

    $this->actingAs($admin)->getJson(route('leads.detail', $lead))
        ->assertOk()
        ->assertJsonPath('lead.id', $lead->id)
        ->assertJsonPath('lead.name', 'Acme Lead')
        ->assertJsonStructure([
            'lead',
            'stages',
            'owners' => [['value', 'label']],
            'allTags',
            'mentionables',
        ])
        ->assertJsonPath('owners.0.value', $admin->id);
});

test('the detail endpoint enforces the same visibility as show', function () {
    $member = User::factory()->member()->create();
    $lead = Lead::factory()->ownedBy(User::factory()->member()->create())->create();

    $this->actingAs($member)->getJson(route('leads.detail', $lead))->assertForbidden();
});

test('a manual lead can be created', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)->post(route('leads.store'), [
        'name' => 'New Person',
        'email' => 'new@person.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('leads', ['email' => 'new@person.com', 'source' => 'manual', 'stage' => 'new']);
});

test('creating a duplicate email warns unless confirmed', function () {
    $user = User::factory()->member()->create();
    Lead::factory()->ownedBy($user)->create(['email' => 'dupe@person.com']);

    $this->actingAs($user)->post(route('leads.store'), [
        'name' => 'Dup',
        'email' => 'dupe@person.com',
    ])->assertSessionHasErrors('email');

    $this->actingAs($user)->post(route('leads.store'), [
        'name' => 'Dup',
        'email' => 'dupe@person.com',
        'confirmed' => true,
    ])->assertRedirect();

    expect(Lead::where('email', 'dupe@person.com')->count())->toBe(2);
});

test('a stage change writes a stage_change activity and stamps lifecycle timestamps', function () {
    $user = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($user)->stage(Stage::New)->create([
        'first_contacted_at' => null,
        'converted_at' => null,
    ]);

    $this->actingAs($user)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Contacted->value,
    ])->assertRedirect();

    $lead->refresh();
    expect($lead->stage)->toBe(Stage::Contacted)
        ->and($lead->first_contacted_at)->not->toBeNull();
    expect($lead->activities()->where('type', ActivityType::StageChange->value)->count())->toBe(1);
});

test('reaching won sets converted_at and requires a win reason', function () {
    $user = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($user)->stage(Stage::InProgress)->create(['converted_at' => null]);

    // Missing win_reason fails.
    $this->actingAs($user)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Won->value,
    ])->assertSessionHasErrors('win_reason');

    $this->actingAs($user)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Won->value,
        'win_reason' => 'best_fit',
    ])->assertRedirect();

    expect($lead->refresh()->converted_at)->not->toBeNull();
});

test('moving to lost requires a lost reason', function () {
    $user = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($user)->stage(Stage::Contacted)->create();

    $this->actingAs($user)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Lost->value,
    ])->assertSessionHasErrors('lost_reason');
});

test('an unassigned lead can be claimed but an owned one cannot', function () {
    $member = User::factory()->member()->create();
    $unassigned = Lead::factory()->unassigned()->create();
    $owned = Lead::factory()->ownedBy(User::factory()->member()->create())->create();

    $this->actingAs($member)->post(route('leads.claim', $unassigned))->assertRedirect();
    expect($unassigned->refresh()->owner_id)->toBe($member->id);
    expect($unassigned->activities()->where('type', ActivityType::System->value)->count())->toBe(1);

    // Owned by someone else and not visible → forbidden.
    $this->actingAs($member)->post(route('leads.claim', $owned))->assertForbidden();
});

test('managers can reassign but members cannot', function () {
    $manager = User::factory()->manager()->create();
    $member = User::factory()->member()->create();
    $target = User::factory()->member()->create();
    $lead = Lead::factory()->unassigned()->create();

    $this->actingAs($member)->post(route('leads.reassign', $lead), ['owner_id' => $member->id])
        ->assertForbidden();

    $this->actingAs($manager)->post(route('leads.reassign', $lead), ['owner_id' => $target->id])
        ->assertRedirect();
    expect($lead->refresh()->owner_id)->toBe($target->id);
});
