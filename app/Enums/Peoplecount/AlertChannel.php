<?php

namespace App\Enums\Peoplecount;

/**
 * Delivery channels for Peoplecount Alerts.
 */
enum AlertChannel: string
{
    case Vonage = 'vonage';
    case Email = 'email';
}
