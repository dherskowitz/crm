<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Concerns\MailAndDatabaseChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutomationAlert extends Notification implements ShouldQueue
{
    use MailAndDatabaseChannels, Queueable;

    public function __construct(
        public Lead $lead,
        public string $alert,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Automation: '.$this->alert)
            ->line($this->alert)
            ->line("Lead: {$this->lead->name}")
            ->action('View lead', route('leads.show', $this->lead));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'automation',
            'lead_id' => $this->lead->id,
            'message' => $this->alert,
        ];
    }
}
