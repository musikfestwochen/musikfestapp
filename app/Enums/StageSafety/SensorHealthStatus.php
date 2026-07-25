<?php

declare(strict_types=1);

namespace App\Enums\StageSafety;

enum SensorHealthStatus: string
{
    case Fresh = 'fresh';
    case Stale = 'stale';
    case NeverSeen = 'never_seen';
    case Archived = 'archived';
}
