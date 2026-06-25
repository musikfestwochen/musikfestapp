<?php

declare(strict_types=1);

use Illuminate\Support\Number;

return [

    /*
    |--------------------------------------------------------------------------
    | Aggregation Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how peoplecount data is aggregated over time.
    | The granularity determines the time intervals for data aggregation.
    |
    */

    'aggregation' => [
        /*
        |--------------------------------------------------------------------------
        | Default Aggregation Granularity
        |--------------------------------------------------------------------------
        |
        | The default time interval (in minutes) for aggregating peoplecount data.
        | This value determines how data points are grouped together for analysis.
        |
        */
        'granularity_minutes' => Number::clamp((int) env('PEOPLECOUNT_AGGREGATION_GRANULARITY', 1), min: 1, max: PHP_INT_MAX),
    ],
];
