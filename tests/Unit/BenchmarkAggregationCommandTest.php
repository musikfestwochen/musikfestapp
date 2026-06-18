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

test('it supports table only output and removes the disposable sqlite benchmark database', function () {
    $benchmarkDirectory = storage_path('app/benchmarks');
    $sqliteFilesBefore = glob($benchmarkDirectory.'/*.sqlite') ?: [];
    $jsonFilesBefore = glob($benchmarkDirectory.'/peoplecount-benchmark-*.json') ?: [];

    $this->artisan('peoplecount:benchmark', [
        '--scenario' => 'small',
        '--iterations' => 1,
        '--db' => 'sqlite',
        '--output' => 'table',
        '--no-interaction' => true,
    ])->assertExitCode(Command::SUCCESS);

    $sqliteFilesAfter = glob($benchmarkDirectory.'/*.sqlite') ?: [];
    $jsonFilesAfter = glob($benchmarkDirectory.'/peoplecount-benchmark-*.json') ?: [];

    expect($sqliteFilesAfter)->toEqualCanonicalizing($sqliteFilesBefore);
    expect($jsonFilesAfter)->toEqualCanonicalizing($jsonFilesBefore);
});
