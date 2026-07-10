<?php

use App\Enums\Role;
use App\Models\Activity;
use App\Models\AutomationRule;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadFormSubmission;
use App\Models\SavedView;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds a populated dataset across every model', function () {
    $this->seed(DatabaseSeeder::class);

    // Multiple users covering each role.
    expect(User::where('role', Role::Admin)->count())->toBeGreaterThanOrEqual(1)
        ->and(User::where('role', Role::Manager)->count())->toBeGreaterThanOrEqual(2)
        ->and(User::where('role', Role::Member)->count())->toBeGreaterThanOrEqual(2);

    // Core CRM records.
    expect(Company::count())->toBeGreaterThan(0)
        ->and(Lead::withoutGlobalScopes()->count())->toBeGreaterThan(0)
        ->and(Activity::count())->toBeGreaterThan(0)
        ->and(Task::count())->toBeGreaterThan(0)
        ->and(AutomationRule::count())->toBeGreaterThan(0)
        ->and(SavedView::count())->toBeGreaterThan(0)
        ->and(LeadFormSubmission::count())->toBeGreaterThan(0);

    // A claimable (unassigned) queue, won deals, and archived leads all exist.
    expect(Lead::withoutGlobalScopes()->whereNull('owner_id')->exists())->toBeTrue()
        ->and(Lead::withoutGlobalScopes()->whereNotNull('converted_at')->exists())->toBeTrue()
        ->and(Lead::withoutGlobalScopes()->whereNotNull('archived_at')->exists())->toBeTrue();

    // Pinned notes and @mentions are wired up.
    expect(Activity::where('is_pinned', true)->exists())->toBeTrue()
        ->and(Activity::has('mentionedUsers')->exists())->toBeTrue();
});

it('is idempotent and does not duplicate the dataset on re-run', function () {
    $this->seed(DatabaseSeeder::class);
    $leadCount = Lead::withoutGlobalScopes()->count();

    $this->seed(DatabaseSeeder::class);

    expect(Lead::withoutGlobalScopes()->count())->toBe($leadCount);
});
