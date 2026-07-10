<?php

use App\Actions\Invitations\SendUserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Password;

test('the set-password page renders for an invited user', function () {
    $user = User::factory()->invited()->create();
    $token = Password::broker(SendUserInvitation::BROKER)->createToken($user);

    $this->get(route('invitation.setup', ['token' => $token, 'email' => $user->email]))
        ->assertOk();
});

test('an invited user can set their password and is activated and logged in', function () {
    $user = User::factory()->invited()->create();
    $token = Password::broker(SendUserInvitation::BROKER)->createToken($user);

    $response = $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user->fresh());

    $fresh = $user->fresh();
    expect($fresh->password)->not->toBeNull()
        ->and($fresh->invitation_accepted_at)->not->toBeNull()
        ->and($fresh->email_verified_at)->not->toBeNull()
        ->and($fresh->hasPendingInvitation())->toBeFalse();
});

test('an invalid token is rejected', function () {
    $user = User::factory()->invited()->create();

    $this->post(route('invitation.store'), [
        'token' => 'totally-invalid-token',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->fresh()->hasPendingInvitation())->toBeTrue();
});

test('the token cannot be reused after acceptance', function () {
    $user = User::factory()->invited()->create();
    $token = Password::broker(SendUserInvitation::BROKER)->createToken($user);

    $payload = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    $this->post(route('invitation.store'), $payload)->assertRedirect(route('dashboard'));

    auth()->logout();

    $this->post(route('invitation.store'), $payload)->assertSessionHasErrors('email');
});

test('the invitation action issues a token and stamps invited_at', function () {
    $user = User::factory()->create(['invited_at' => null, 'password' => null]);

    app(SendUserInvitation::class)($user);

    expect($user->fresh()->invited_at)->not->toBeNull();
    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});
