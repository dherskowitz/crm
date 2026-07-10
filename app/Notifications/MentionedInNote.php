<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\Concerns\MailAndDatabaseChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionedInNote extends Notification implements ShouldQueue
{
    use MailAndDatabaseChannels, Queueable;

    public function __construct(
        public Activity $activity,
        public User $mentioner,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->mentioner->name} mentioned you")
            ->line("{$this->mentioner->name} mentioned you in a note on {$this->activity->lead->name}.")
            ->action('View lead', route('leads.show', $this->activity->lead_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'lead_id' => $this->activity->lead_id,
            'message' => "{$this->mentioner->name} mentioned you in a note",
        ];
    }
}
