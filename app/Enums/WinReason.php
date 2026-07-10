<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WinReason: string
{
    use HasOptions;

    case BestFit = 'best_fit';
    case Price = 'price';
    case Relationship = 'relationship';
    case Timing = 'timing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BestFit => 'Best Fit',
            self::Price => 'Price',
            self::Relationship => 'Relationship',
            self::Timing => 'Timing',
            self::Other => 'Other',
        };
    }
}
