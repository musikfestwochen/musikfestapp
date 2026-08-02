<?php

use Illuminate\Console\Command;

test('it rejects an unknown benchmark scenario before running', function () {
    $this->artisan('peoplecount:benchmark', [
        '--scenario' => 'unknown',
        '--no-interaction' => true,
    ])
        ->expectsPromptsError('Unknown scenario: unknown. Available: small, medium, large, xlarge')
        ->assertExitCode(Command::FAILURE);
});

test('it rejects an unknown benchmark database before running', function () {
    $this->artisan('peoplecount:benchmark', [
        '--db' => 'postgres',
        '--no-interaction' => true,
    ])
        ->expectsPromptsError('Unknown database: postgres. Available: sqlite, mariadb')
        ->assertExitCode(Command::FAILURE);
});

test('it rejects invalid benchmark iterations before running', function () {
    $this->artisan('peoplecount:benchmark', [
        '--iterations' => 0,
        '--no-interaction' => true,
    ])
        ->expectsPromptsError('Iterations must be between 1 and 50.')
        ->assertExitCode(Command::FAILURE);
});

test('it rejects an unknown benchmark output format before running', function () {
    $this->artisan('peoplecount:benchmark', [
        '--output' => 'xml',
        '--no-interaction' => true,
    ])
        ->expectsPromptsError('Unknown output format: xml. Available: json, table, both')
        ->assertExitCode(Command::FAILURE);
});

test('it rejects an unknown mariadb source before running', function () {
    $this->artisan('peoplecount:benchmark', [
        '--mariadb' => 'ci',
        '--no-interaction' => true,
    ])
        ->expectsPromptsError('Unknown MariaDB source: ci. Available: docker, external')
        ->assertExitCode(Command::FAILURE);
});

test('it rejects benchmark runs with xdebug enabled unless explicitly allowed', function () {
    if (! extension_loaded('xdebug')) {
        $this->markTestSkipped('Xdebug is not loaded in this PHP process.');
    }

    $previousMode = getenv('XDEBUG_MODE');
    putenv('XDEBUG_MODE=debug');

    try {
        $this->artisan('peoplecount:benchmark', [
            '--scenario' => 'small',
            '--iterations' => 1,
            '--db' => 'sqlite',
            '--output' => 'table',
            '--no-interaction' => true,
        ])
            ->expectsPromptsError('Xdebug is enabled. Re-run with XDEBUG_MODE=off for consistent benchmark results, or pass --allow-xdebug when profiling intentionally.')
            ->assertExitCode(Command::FAILURE);
    } finally {
        $previousMode === false
            ? putenv('XDEBUG_MODE')
            : putenv('XDEBUG_MODE='.$previousMode);
    }
});

test('it supports table only output and removes the disposable sqlite benchmark database', function () {
    $benchmarkDirectory = storage_path('app/benchmarks');
    $sqliteFilesBefore = glob($benchmarkDirectory.'/*.sqlite') ?: [];
    $jsonFilesBefore = glob($benchmarkDirectory.'/peoplecount-benchmark-*.json') ?: [];

    $this->artisan('peoplecount:benchmark', [
        '--scenario' => 'small',
        '--iterations' => 1,
        '--db' => 'sqlite',
        '--output' => 'table',
        '--allow-xdebug' => true,
        '--no-interaction' => true,
    ])->assertExitCode(Command::SUCCESS);

    $sqliteFilesAfter = glob($benchmarkDirectory.'/*.sqlite') ?: [];
    $jsonFilesAfter = glob($benchmarkDirectory.'/peoplecount-benchmark-*.json') ?: [];

    expect($sqliteFilesAfter)->toEqualCanonicalizing($sqliteFilesBefore)
        ->and($jsonFilesAfter)->toEqualCanonicalizing($jsonFilesBefore);
});
