<?php

namespace App\Enums\Peoplecount;

/**
 * Delivery channels for Peoplecount Alerts.
 */
enum AlertChannel: string
{
    case Vonage = 'vonage';
    case Email = 'email';

    /**
     * Get the display name for the alert channel.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::Vonage => 'SMS',
            self::Email => 'Email',
        };
    }

    /**
     * Get the description for the alert channel.
     */
    public function description(): string
    {
        return match ($this) {
            self::Vonage => 'Send alerts via SMS.',
            self::Email => 'Send alerts via email.',
        };
    }
}
