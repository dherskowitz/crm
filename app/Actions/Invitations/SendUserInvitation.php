<?php

namespace App\Actions\Invitations;

use App\Mail\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendUserInvitation
{
    /**
     * The password broker used to mint long-lived invitation tokens.
     */
    public const BROKER = 'invitations';

    /**
     * Issue (or re-issue) an invitation token for the user and email a
     * branded invitation linking to the set-password page.
     */
    public function __invoke(User $user, ?User $inviter = null): void
    {
        $token = Password::broker(self::BROKER)->createToken($user);

        $user->forceFill(['invited_at' => now()])->save();

        Mail::to($user->email)->send(
            new UserInvitation($user, $token, $inviter?->name),
        );
    }
}
