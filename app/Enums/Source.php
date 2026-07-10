<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Source: string
{
    use HasOptions;

    case Website = 'website';
    case Manual = 'manual';
    case Referral = 'referral';
    case Import = 'import';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Manual => 'Manual',
            self::Referral => 'Referral',
            self::Import => 'Import',
            self::Other => 'Other',
        };
    }
}
