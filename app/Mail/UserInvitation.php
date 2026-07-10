<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $invitee,
        public string $token,
        public ?string $inviterName = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.user-invitation',
            with: [
                'url' => route('invitation.setup', [
                    'token' => $this->token,
                    'email' => $this->invitee->email,
                ]),
                'inviteeName' => $this->invitee->name,
                'inviterName' => $this->inviterName,
                'appName' => config('app.name'),
            ],
        );
    }
}
