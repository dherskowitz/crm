<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Stage: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case InProgress = 'in_progress';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::InProgress => 'In Progress',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }
}
