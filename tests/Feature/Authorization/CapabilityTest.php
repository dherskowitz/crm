<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('the role capability matrix matches the specification', function () {
    expect(Role::Admin->capabilities())->toEqualCanonicalizing([
        'manage_users', 'view_audit_log', 'delete_leads', 'merge_duplicates',
        'reassign_leads', 'view_all_leads', 'team_reporting', 'export',
    ]);

    expect(Role::Manager->capabilities())->toEqualCanonicalizing([
        'merge_duplicates', 'reassign_leads', 'view_all_leads', 'team_reporting', 'export',
    ]);

    expect(Role::Member->capabilities())->toBe([]);
});

test('gates resolve from the role capability set', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();
    $member = User::factory()->member()->create();

    expect(Gate::forUser($admin)->allows('manage_users'))->toBeTrue();
    expect(Gate::forUser($manager)->allows('manage_users'))->toBeFalse();
    expect(Gate::forUser($manager)->allows('view_all_leads'))->toBeTrue();
    expect(Gate::forUser($member)->allows('view_all_leads'))->toBeFalse();
});

test('the capability map is shared to inertia for the authenticated user', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.can.view_all_leads', true)
            ->where('auth.can.manage_users', false));
});
