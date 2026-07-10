<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FormType: string
{
    use HasOptions;

    case Contact = 'contact';
    case DemoRequest = 'demo_request';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contact',
            self::DemoRequest => 'Demo Request',
            self::Other => 'Other',
        };
    }
}
