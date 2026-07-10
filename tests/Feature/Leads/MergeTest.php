<?php

use App\Actions\Companies\MergeCompanies;
use App\Actions\Companies\UnmergeCompanies;
use App\Actions\Leads\MergeLeads;
use App\Actions\Leads\UnmergeLeads;
use App\Enums\ActivityType;
use App\Enums\Stage;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Scopes\NotMergedScope;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;

test('merging re-points children and soft-merges the loser', function () {
    $actor = User::factory()->admin()->create();
    $owner = User::factory()->member()->create();

    $survivor = Lead::factory()->ownedBy($owner)->create([
        'email' => 'keep@example.com',
        'phone' => null,
    ]);
    $loser = Lead::factory()->create([
        'email' => 'dupe@example.com',
        'phone' => '555-1234',
    ]);

    $activity = Activity::factory()->for($loser)->create();
    $task = Task::factory()->for($loser)->create();
    $tag = Tag::factory()->create();
    $loser->tags()->attach($tag);
    $attachment = Attachment::factory()->create([
        'attachable_type' => (new Lead)->getMorphClass(),
        'attachable_id' => $loser->id,
    ]);

    app(MergeLeads::class)($survivor, $loser, $actor);

    expect($activity->fresh()->lead_id)->toBe($survivor->id)
        ->and($task->fresh()->lead_id)->toBe($survivor->id)
        ->and($attachment->fresh()->attachable_id)->toBe($survivor->id)
        ->and($survivor->tags()->whereKey($tag->id)->exists())->toBeTrue();

    // Loser keeps its own email (survivor wins) but contributes its phone.
    $freshSurvivor = $survivor->fresh();
    expect($freshSurvivor->email)->toBe('keep@example.com')
        ->and($freshSurvivor->phone)->toBe('555-1234');

    // The loser is hidden from default queries but still present.
    expect(Lead::find($loser->id))->toBeNull()
        ->and(Lead::withoutGlobalScope(NotMergedScope::class)->find($loser->id)->merged_into_id)
        ->toBe($survivor->id);

    // A merge activity is written onto the survivor.
    $merge = Activity::where('lead_id', $survivor->id)
        ->where('type', ActivityType::Merge)
        ->first();
    expect($merge)->not->toBeNull()
        ->and($merge->user_id)->toBe($actor->id)
        ->and($merge->body['merged_lead_id'])->toBe($loser->id);
});

test('a member without the capability cannot merge leads', function () {
    $member = User::factory()->member()->create();
    $survivor = Lead::factory()->create();
    $loser = Lead::factory()->create();

    $this->actingAs($member)
        ->post(route('duplicates.leads'), [
            'survivor_id' => $survivor->id,
            'loser_id' => $loser->id,
        ])
        ->assertForbidden();

    expect($loser->fresh()->merged_into_id)->toBeNull();
});

test('an admin can merge leads through the endpoint', function () {
    $admin = User::factory()->admin()->create();
    $survivor = Lead::factory()->create(['email' => 'a@example.com']);
    $loser = Lead::factory()->create(['email' => 'a@example.com']);

    $this->actingAs($admin)
        ->post(route('duplicates.leads'), [
            'survivor_id' => $survivor->id,
            'loser_id' => $loser->id,
        ])
        ->assertRedirect();

    expect(Lead::withoutGlobalScope(NotMergedScope::class)->find($loser->id)->merged_into_id)
        ->toBe($survivor->id);
});

test('the duplicates page lists clusters sharing an email', function () {
    $admin = User::factory()->admin()->create();
    Lead::factory()->create(['email' => 'same@example.com']);
    Lead::factory()->create(['email' => 'same@example.com']);
    Lead::factory()->create(['email' => 'unique@example.com']);

    $this->actingAs($admin)->get(route('duplicates.index'))
        ->assertInertia(fn ($page) => $page
            ->component('duplicates/index')
            ->has('leadClusters', 1)
            ->has('leadClusters.0.leads', 2));
});

test('a member cannot view the duplicates page', function () {
    $member = User::factory()->member()->create();

    $this->actingAs($member)->get(route('duplicates.index'))->assertForbidden();
});

test('company merge re-points leads onto the survivor', function () {
    $survivor = Company::factory()->create(['domain' => null]);
    $loser = Company::factory()->create(['domain' => 'loser.test']);

    $lead = Lead::factory()->forCompany($loser)->create();

    app(MergeCompanies::class)($survivor, $loser);

    expect($lead->fresh()->company_id)->toBe($survivor->id)
        ->and($survivor->fresh()->domain)->toBe('loser.test')
        ->and(Company::find($loser->id))->toBeNull()
        ->and(Company::withoutGlobalScope(NotMergedScope::class)->find($loser->id)->merged_into_id)
        ->toBe($survivor->id);
});

test('companies sharing a normalized name appear as a cluster', function () {
    $admin = User::factory()->admin()->create();
    Company::factory()->create(['name' => 'Acme Inc']);
    Company::factory()->create(['name' => 'ACME, Inc.']);

    $this->actingAs($admin)->get(route('duplicates.index'))
        ->assertInertia(fn ($page) => $page
            ->has('companyClusters', 1)
            ->has('companyClusters.0.companies', 2));
});

test('the loser stage does not affect the survivor stage', function () {
    $actor = User::factory()->admin()->create();
    $survivor = Lead::factory()->stage(Stage::Qualified)->create();
    $loser = Lead::factory()->stage(Stage::New)->create();

    app(MergeLeads::class)($survivor, $loser, $actor);

    expect($survivor->fresh()->stage)->toBe(Stage::Qualified);
});

test('unmerging a lead fully reverses the merge', function () {
    $actor = User::factory()->admin()->create();
    $survivor = Lead::factory()->create(['email' => 'keep@example.com', 'phone' => null]);
    $loser = Lead::factory()->create(['email' => 'dupe@example.com', 'phone' => '555-1234']);

    $activity = Activity::factory()->for($loser)->create();
    $task = Task::factory()->for($loser)->create();
    $tag = Tag::factory()->create();
    $loser->tags()->attach($tag);
    $attachment = Attachment::factory()->create([
        'attachable_type' => (new Lead)->getMorphClass(),
        'attachable_id' => $loser->id,
    ]);

    app(MergeLeads::class)($survivor, $loser, $actor);

    $hidden = fn () => Lead::withoutGlobalScope(NotMergedScope::class)->find($loser->id);
    app(UnmergeLeads::class)($hidden());

    // Children moved back onto the loser.
    expect($activity->fresh()->lead_id)->toBe($loser->id)
        ->and($task->fresh()->lead_id)->toBe($loser->id)
        ->and($attachment->fresh()->attachable_id)->toBe($loser->id);

    // The backfilled field is nulled and the added tag is detached from the survivor.
    $freshSurvivor = $survivor->fresh();
    expect($freshSurvivor->phone)->toBeNull()
        ->and($freshSurvivor->tags()->whereKey($tag->id)->exists())->toBeFalse();

    // The merge activity is gone and the loser is visible again.
    expect(Activity::where('type', ActivityType::Merge)->exists())->toBeFalse()
        ->and(Lead::find($loser->id))->not->toBeNull()
        ->and($loser->fresh()->merged_into_id)->toBeNull();
});

test('an admin can unmerge leads through the endpoint but a member cannot', function () {
    $admin = User::factory()->admin()->create();
    $survivor = Lead::factory()->create(['email' => 'a@example.com']);
    $loser = Lead::factory()->create(['email' => 'a@example.com']);
    app(MergeLeads::class)($survivor, $loser, $admin);

    $member = User::factory()->member()->create();
    $this->actingAs($member)
        ->post(route('duplicates.leads.unmerge'), ['loser_id' => $loser->id])
        ->assertForbidden();
    expect($loser->fresh()->merged_into_id)->toBe($survivor->id);

    $this->actingAs($admin)
        ->post(route('duplicates.leads.unmerge'), ['loser_id' => $loser->id])
        ->assertRedirect();
    expect(Lead::find($loser->id)?->merged_into_id)->toBeNull();
});

test('unmerging a company moves its leads back and nulls a backfilled domain', function () {
    $survivor = Company::factory()->create(['domain' => null]);
    $loser = Company::factory()->create(['domain' => 'loser.test']);
    $lead = Lead::factory()->forCompany($loser)->create();

    app(MergeCompanies::class)($survivor, $loser);
    $hidden = Company::withoutGlobalScope(NotMergedScope::class)->find($loser->id);
    app(UnmergeCompanies::class)($hidden);

    expect($lead->fresh()->company_id)->toBe($loser->id)
        ->and($survivor->fresh()->domain)->toBeNull()
        ->and(Company::find($loser->id))->not->toBeNull()
        ->and($loser->fresh()->merged_into_id)->toBeNull();
});
