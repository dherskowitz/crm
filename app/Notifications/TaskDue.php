<?php

namespace App\Notifications;

use App\Models\Task;
use App\Notifications\Concerns\MailAndDatabaseChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDue extends Notification implements ShouldQueue
{
    use MailAndDatabaseChannels, Queueable;

    public function __construct(public Task $task) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A task is due')
            ->line("Your task \"{$this->task->title}\" is due.")
            ->action('View lead', route('leads.show', $this->task->lead_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_due',
            'lead_id' => $this->task->lead_id,
            'task_id' => $this->task->id,
            'message' => "Task due: {$this->task->title}",
        ];
    }
}
