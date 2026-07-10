<?php

use App\Enums\Stage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('a member dashboard only counts their own and unassigned leads', function () {
    $member = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    Lead::factory()->ownedBy($member)->create();
    Lead::factory()->ownedBy($member)->won()->create();
    Lead::factory()->unassigned()->create();

    // Not visible to the member.
    Lead::factory()->ownedBy($other)->create();
    Lead::factory()->ownedBy($other)->won()->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('conversion.total', 3)
            ->where('conversion.won', 1)
        );
});

test('the leaderboard is absent for members and present for managers', function () {
    $member = User::factory()->member()->create();
    $manager = User::factory()->manager()->create();

    Lead::factory()->ownedBy($member)->won()->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->missing('leaderboard')
        );

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('leaderboard')
        );
});

test('the leaderboard aggregates won leads per owner', function () {
    $manager = User::factory()->manager()->create();
    $rep = User::factory()->member()->create(['name' => 'Top Rep']);

    Lead::factory()->ownedBy($rep)->won()->create(['value' => 1000]);
    Lead::factory()->ownedBy($rep)->won()->create(['value' => 500]);
    Lead::factory()->ownedBy($rep)->stage(Stage::Qualified)->create();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('leaderboard.0.owner', 'Top Rep')
            ->where('leaderboard.0.total', 3)
            ->where('leaderboard.0.won', 2)
            ->where('leaderboard.0.value', 1500)
        );
});

test('cold leads surface stale, non-terminal, non-archived leads', function () {
    $admin = User::factory()->admin()->create();

    $cold = Lead::factory()->stage(Stage::Qualified)->create([
        'last_activity_at' => now()->subDays(config('crm.cold_lead_days') + 5),
    ]);

    // Recent activity — not cold.
    Lead::factory()->stage(Stage::Qualified)->create([
        'last_activity_at' => now(),
    ]);

    // Terminal — excluded even if stale.
    Lead::factory()->won()->create([
        'last_activity_at' => now()->subDays(config('crm.cold_lead_days') + 5),
    ]);

    // Archived — excluded.
    Lead::factory()->stage(Stage::Qualified)->archived()->create([
        'last_activity_at' => now()->subDays(config('crm.cold_lead_days') + 5),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('coldLeads', 1)
            ->where('coldLeads.0.id', $cold->id)
        );
});

test('cold leads respect lead visibility for members', function () {
    $member = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    Lead::factory()->ownedBy($member)->stage(Stage::Qualified)->create([
        'last_activity_at' => now()->subDays(config('crm.cold_lead_days') + 5),
    ]);

    // Owned by someone else — invisible to the member.
    Lead::factory()->ownedBy($other)->stage(Stage::Qualified)->create([
        'last_activity_at' => now()->subDays(config('crm.cold_lead_days') + 5),
    ]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('coldLeads', 1));
});

test('the date range filters which leads are counted', function () {
    $admin = User::factory()->admin()->create();

    Lead::factory()->create(['created_at' => now()->subDays(2)]);
    Lead::factory()->create(['created_at' => now()->subDays(60)]);

    // Default range (last 30 days) sees only the recent lead.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('conversion.total', 1));

    // Wider explicit range sees both.
    $this->actingAs($admin)
        ->get(route('dashboard', [
            'from' => now()->subDays(90)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('conversion.total', 2));
});

test('won value sums only won leads in the range', function () {
    $admin = User::factory()->admin()->create();

    Lead::factory()->won()->create(['value' => 2500]);
    Lead::factory()->won()->create(['value' => 1500]);
    Lead::factory()->stage(Stage::Qualified)->create(['value' => 9999]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('wonValue', 4000));
});

test('the date range filter narrows the dashboard data', function () {
    $member = User::factory()->member()->create();

    // Inside a tight window around 2026-03-15.
    Lead::factory()->ownedBy($member)->create(['created_at' => '2026-03-15 12:00:00']);
    Lead::factory()->ownedBy($member)->won()->create(['created_at' => '2026-03-16 12:00:00']);

    // Outside the window.
    Lead::factory()->ownedBy($member)->create(['created_at' => '2026-01-01 12:00:00']);
    Lead::factory()->ownedBy($member)->won()->create(['created_at' => '2026-05-01 12:00:00']);

    $this->actingAs($member)
        ->get(route('dashboard', ['from' => '2026-03-14', 'to' => '2026-03-17']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('range.from', '2026-03-14')
            ->where('range.to', '2026-03-17')
            ->where('conversion.total', 2)
            ->where('conversion.won', 1)
        );
});
