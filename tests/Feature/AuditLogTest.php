<?php

use App\Enums\Role;
use App\Enums\Stage;
use App\Models\Lead;
use App\Models\User;
use Spatie\Activitylog\Models\Activity as AuditActivity;

test('updating a lead writes a spatie audit entry capturing the change', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($admin)->stage(Stage::New)->create();

    $before = AuditActivity::query()
        ->where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->count();

    $this->actingAs($admin)->patch(route('leads.update', $lead), [
        'name' => $lead->name,
        'stage' => Stage::Contacted->value,
    ])->assertRedirect();

    $entries = AuditActivity::query()
        ->where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->get();

    expect($entries->count() - $before)->toBeGreaterThanOrEqual(1);

    $latest = $entries->sortByDesc('id')->first();

    $changes = $latest->attribute_changes->toArray();

    expect($latest->description)->toBe('updated')
        ->and($changes['attributes']['stage'])->toBe(Stage::Contacted->value)
        ->and($changes['old']['stage'])->toBe(Stage::New->value)
        ->and($latest->causer_id)->toBe($admin->id);
});

test('the audit log is forbidden for non-admins', function (string $role) {
    $user = User::factory()->state(['role' => $role])->create();

    $this->actingAs($user)
        ->get(route('audit.index'))
        ->assertForbidden();
})->with([Role::Manager->value, Role::Member->value]);

test('admins can view the audit log screen with mapped entries', function () {
    $admin = User::factory()->admin()->create();
    $lead = Lead::factory()->ownedBy($admin)->stage(Stage::New)->create();
    $lead->update(['stage' => Stage::Contacted]);

    $this->actingAs($admin)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('audit/index')
            ->has('entries')
            ->has('entries.0', fn ($entry) => $entry
                ->has('id')
                ->has('description')
                ->has('subject_type')
                ->has('subject_id')
                ->has('causer')
                ->has('changes')
                ->has('changes.attributes')
                ->has('changes.old')
                ->has('log_name')
                ->has('created_at')
            )
        );
});
