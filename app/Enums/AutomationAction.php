<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AutomationAction: string
{
    use HasOptions;

    case CreateTask = 'create_task';
    case SendNotification = 'send_notification';

    public function label(): string
    {
        return match ($this) {
            self::CreateTask => 'Create a task',
            self::SendNotification => 'Send a notification',
        };
    }
}
