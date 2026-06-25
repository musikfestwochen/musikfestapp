<?php

use Illuminate\Support\Env;

it('clamps peoplecount aggregation granularity to one minute minimum', function (string $value) {
    $key = 'PEOPLECOUNT_AGGREGATION_GRANULARITY';
    $previousEnv = $_ENV[$key] ?? null;
    $previousServer = $_SERVER[$key] ?? null;
    $previousPutenv = getenv($key);

    try {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
        Env::enablePutenv();

        $config = require config_path('peoplecount.php');

        expect($config['aggregation']['granularity_minutes'])->toBe(1);
    } finally {
        if ($previousEnv === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $previousEnv;
        }

        if ($previousServer === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $previousServer;
        }

        if ($previousPutenv === false) {
            putenv($key);
        } else {
            putenv("{$key}={$previousPutenv}");
        }

        Env::enablePutenv();
    }
})->with([
    'zero' => '0',
    'negative' => '-5',
    'non-numeric' => 'nope',
]);
