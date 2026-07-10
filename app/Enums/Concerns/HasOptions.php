<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * The enum cases as value/label option pairs for select inputs.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
