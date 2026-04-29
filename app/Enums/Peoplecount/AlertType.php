<?php

declare(strict_types=1);

namespace App\Enums\Peoplecount;

/**
 * Peoplecount Alert types.
 */
enum AlertType: string
{
    case OccupancyAlert = 'occupancy_alert';

    /**
     * Get the display name for the alert type.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::OccupancyAlert => 'Occupancy Alert',
        };
    }

    /**
     * Get the description for the alert type.
     */
    public function description(): string
    {
        return match ($this) {
            self::OccupancyAlert => 'Alert when occupancy exceeds a specified threshold.',
        };
    }
}
