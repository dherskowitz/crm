<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LostReason: string
{
    use HasOptions;

    case NotInterested = 'not_interested';
    case NoBudget = 'no_budget';
    case LostToCompetitor = 'lost_to_competitor';
    case NoResponse = 'no_response';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotInterested => 'Not Interested',
            self::NoBudget => 'No Budget',
            self::LostToCompetitor => 'Lost to Competitor',
            self::NoResponse => 'No Response',
            self::Other => 'Other',
        };
    }
}
