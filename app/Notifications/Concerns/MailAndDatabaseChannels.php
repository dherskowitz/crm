<?php

namespace App\Notifications\Concerns;

trait MailAndDatabaseChannels
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
