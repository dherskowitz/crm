<?php

use App\Enums\Role;
use App\Mail\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('admins can view the user management screen', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk();
});

test('managers and members cannot access user management', function (string $role) {
    $user = User::factory()->state(['role' => $role])->create();

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
})->with([Role::Manager->value, Role::Member->value]);

test('admins can invite a user, creating a pending account and sending an invitation', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'JANE@acme.com',
            'role' => Role::Member->value,
        ])
        ->assertRedirect();

    $invitee = User::where('email', 'jane@acme.com')->first();

    expect($invitee)->not->toBeNull()
        ->and($invitee->password)->toBeNull()
        ->and($invitee->role)->toBe(Role::Member)
        ->and($invitee->hasPendingInvitation())->toBeTrue();

    Mail::assertQueued(UserInvitation::class, fn (UserInvitation $mail) => $mail->hasTo('jane@acme.com'));
});

test('inviting a duplicate email is rejected', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@acme.com']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Dup',
            'email' => 'taken@acme.com',
            'role' => Role::Member->value,
        ])
        ->assertSessionHasErrors('email');
});

test('non-admins cannot invite users', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->post(route('users.store'), [
            'name' => 'Jane',
            'email' => 'jane@acme.com',
            'role' => Role::Member->value,
        ])
        ->assertForbidden();
});

test('admins can change another user role', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();

    $this->actingAs($admin)
        ->patch(route('users.update', $member), ['role' => Role::Manager->value])
        ->assertRedirect();

    expect($member->fresh()->role)->toBe(Role::Manager);
});

test('admins cannot change their own role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('users.update', $admin), ['role' => Role::Member->value])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('admins can resend a pending invitation', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->invited()->create();

    $this->actingAs($admin)
        ->post(route('users.resend', $pending))
        ->assertRedirect();

    Mail::assertQueued(UserInvitation::class);
});

test('resending to an already accepted user is rejected', function () {
    $admin = User::factory()->admin()->create();
    $active = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('users.resend', $active))
        ->assertSessionHasErrors('user');
});

test('admins can remove another user but not themselves', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->member()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $member))
        ->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $member->id]);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertSessionHasErrors('user');
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});
