<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AutomationTrigger: string
{
    use HasOptions;

    case StageChange = 'stage_change';
    case LeadIdle = 'lead_idle';

    public function label(): string
    {
        return match ($this) {
            self::StageChange => 'When stage changes',
            self::LeadIdle => 'When a lead is idle for N days',
        };
    }
}
