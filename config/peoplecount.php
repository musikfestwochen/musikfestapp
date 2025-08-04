<?php

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
        'granularity_minutes' => env('PEOPLECOUNT_AGGREGATION_GRANULARITY', 10),
    ],
];
