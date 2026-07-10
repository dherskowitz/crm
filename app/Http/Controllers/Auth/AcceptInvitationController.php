<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Invitations\SendUserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

class AcceptInvitationController extends Controller
{
    /**
     * Show the set-password page for an invited user.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/accept-invitation', [
            'token' => (string) $request->query('token'),
            'email' => (string) $request->query('email'),
        ]);
    }

    /**
     * Set the initial password and activate the invited account.
     */
    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $status = Password::broker(SendUserInvitation::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'invitation_accepted_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Auth::login($user);
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }
}
