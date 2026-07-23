<?php

use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use Database\Seeders\DatabaseSeeder;

it('does not seed production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        (new DatabaseSeeder)->run();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    expect(Organization::query()->count())->toBe(0);
});

it('seeds development organizations with Stage Safety sensors', function () {
    (new DatabaseSeeder)->run();

    expect(Organization::query()->count())->toBe(3)
        ->and(Sensor::query()->count())->toBeBetween(9, 18)
        ->and(Sensor::query()->pluck('identifier')->every(
            fn (string $identifier): bool => preg_match('/\A[0-9A-F]{6}\z/', $identifier) === 1,
        ))->toBeTrue();
});
