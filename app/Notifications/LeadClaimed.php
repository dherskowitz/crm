<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\Concerns\MailAndDatabaseChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadClaimed extends Notification implements ShouldQueue
{
    use MailAndDatabaseChannels, Queueable;

    public function __construct(
        public Lead $lead,
        public User $claimer,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A queued lead was claimed')
            ->line("{$this->claimer->name} claimed {$this->lead->name} from the queue.")
            ->action('View lead', route('leads.show', $this->lead));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'claimed',
            'lead_id' => $this->lead->id,
            'message' => "{$this->claimer->name} claimed {$this->lead->name}",
        ];
    }
}
