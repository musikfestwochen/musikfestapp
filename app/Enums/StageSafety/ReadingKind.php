<?php

declare(strict_types=1);

namespace App\Enums\StageSafety;

enum ReadingKind: string
{
    case WindAverage = 'wind_average';
    case WindGust = 'wind_gust';
}
