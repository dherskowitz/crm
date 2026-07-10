<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Concerns\MailAndDatabaseChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLeadInQueue extends Notification implements ShouldQueue
{
    use MailAndDatabaseChannels, Queueable;

    public function __construct(public Lead $lead) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A new lead is in the queue')
            ->line("{$this->lead->name} just came in from the website.")
            ->action('View lead', route('leads.show', $this->lead));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_lead',
            'lead_id' => $this->lead->id,
            'message' => "New lead in the queue: {$this->lead->name}",
        ];
    }
}
