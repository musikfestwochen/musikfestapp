<?php

namespace App\Enums\Peoplecount;

enum Direction: string
{
    case IN = 'in';
    case OUT = 'out';

    /**
     * Get all available cases as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the enum value.
     */
    public function label(): string
    {
        return match ($this) {
            self::IN => 'In',
            self::OUT => 'Out',
        };
    }
}
