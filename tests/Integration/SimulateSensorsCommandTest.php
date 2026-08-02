<?php

use App\Enums\StageSafety\ReadingKind;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor as PeoplecountSensor;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor as StageSafetySensor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('it rejects production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->artisan('sensors:simulate')
            ->expectsPromptsError('Sensor simulation is not allowed in production.')
            ->assertExitCode(Command::FAILURE);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('it rejects an unknown sensor type', function () {
    $this->artisan('sensors:simulate', [
        '--type' => 'temperature',
    ])
        ->expectsPromptsError('Unknown sensor type: temperature. Available: both, peoplecount, stage-safety')
        ->assertExitCode(Command::FAILURE);
});

test('it rejects invalid history hours', function (string $history) {
    $this->artisan('sensors:simulate', [
        '--history' => $history,
    ])
        ->expectsPromptsError('History must be a whole number between 0 and 24 hours.')
        ->assertExitCode(Command::FAILURE);
})->with(['-1', '25', '1.5', 'invalid']);

test('it collects options through the interactive wizard', function () {
    $this->artisan('sensors:simulate')
        ->expectsPromptsIntro('Sensor Simulation')
        ->expectsChoice('Sensor type', 'stage-safety', [
            'both' => 'Both',
            'peoplecount' => 'People count',
            'stage-safety' => 'Stage safety',
        ], strict: true)
        ->expectsChoice('Historical data', '6', [
            '0' => 'None',
            '1' => '1 hour',
            '3' => '3 hours',
            '6' => '6 hours',
            '12' => '12 hours',
            '24' => '24 hours',
        ], strict: true)
        ->expectsConfirmation('Continue generating live data?', 'no')
        ->assertExitCode(Command::SUCCESS);
});

test('it handles an empty sensor database', function () {
    $this->artisan('sensors:simulate', [
        '--type' => 'peoplecount',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(IntervalCount::query()->count())->toBe(0);
});

test('it generates one people-count interval per completed minute and active sensor', function () {
    CarbonImmutable::setTestNow('2026-07-26 12:00:30 UTC');

    $organization = Organization::factory()->create();
    $activeSensors = PeoplecountSensor::factory()->withOrganization($organization)->count(2)->create();
    PeoplecountSensor::factory()->withOrganization($organization)->create(['archived_at' => now()]);
    PeoplecountSensor::factory()->withOrganization($organization)->create()->delete();
    StageSafetySensor::factory()->for($organization)->create();

    $this->artisan('sensors:simulate', [
        '--type' => 'peoplecount',
        '--history' => '1',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(IntervalCount::query()->count())->toBe(120)
        ->and(IntervalCount::query()->distinct()->pluck('sensor_id')->all())
        ->toEqualCanonicalizing($activeSensors->modelKeys())
        ->and(Reading::query()->count())->toBe(0);
});

test('it aggregates simulated people-count history', function () {
    CarbonImmutable::setTestNow('2026-07-26 12:00:30 UTC');

    $organization = Organization::factory()->create();
    $event = Event::factory()->withOrganization($organization)->create([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addHour(),
    ]);
    $area = Area::factory()->withEvent($event)->create();
    $sensor = PeoplecountSensor::factory()->withOrganization($organization)->create();
    Assignment::factory()->create([
        'event_id' => $event->id,
        'area_id' => $area->id,
        'sensor_id' => $sensor->id,
        'active_from' => $event->starts_at,
        'active_to' => $event->ends_at,
        'direction_flipped' => false,
    ]);

    $this->artisan('sensors:simulate', [
        '--type' => 'peoplecount',
        '--history' => '1',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(AreaAggregatedCount::query()->whereBelongsTo($area)->exists())->toBeTrue();

    AreaAggregatedCount::query()->delete();

    $this->artisan('sensors:simulate', [
        '--type' => 'peoplecount',
        '--history' => '1',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(AreaAggregatedCount::query()->whereBelongsTo($area)->exists())->toBeTrue();
});

test('it generates asynchronous pseudo-random stage-safety readings', function () {
    CarbonImmutable::setTestNow('2026-07-26 12:00:30 UTC');

    $organization = Organization::factory()->create();
    $sensors = StageSafetySensor::factory()->for($organization)->count(4)->create();
    $archivedSensor = StageSafetySensor::factory()->for($organization)->create(['archived_at' => now()]);
    PeoplecountSensor::factory()->withOrganization($organization)->create();
    fake()->seed(1234);

    $this->artisan('sensors:simulate', [
        '--type' => 'stage-safety',
        '--history' => '1',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(Reading::query()->where('sensor_id', $archivedSensor->id)->exists())->toBeFalse()
        ->and(IntervalCount::query()->count())->toBe(0);

    $gustSensors = 0;

    foreach ($sensors as $sensor) {
        $averageTimes = Reading::query()
            ->whereBelongsTo($sensor)
            ->where('kind', ReadingKind::WindAverage)
            ->oldest('observed_at')
            ->pluck('observed_at')
            ->map(fn (string $timestamp): CarbonImmutable => CarbonImmutable::parse($timestamp));
        $gustTimes = Reading::query()
            ->whereBelongsTo($sensor)
            ->where('kind', ReadingKind::WindGust)
            ->oldest('observed_at')
            ->pluck('observed_at')
            ->map(fn (string $timestamp): CarbonImmutable => CarbonImmutable::parse($timestamp));

        expect($averageTimes)->not->toBeEmpty()
            ->and($averageTimes->intersect($gustTimes))->toBeEmpty();

        $gustSensors += (int) $gustTimes->isNotEmpty();

        $intervals = $averageTimes
            ->zip($averageTimes->skip(1))
            ->filter(fn (Collection $pair): bool => $pair->get(1) !== null)
            ->map(fn (Collection $pair): int => (int) $pair->get(0)->diffInSeconds($pair->get(1)));

        expect($intervals->every(fn (int $seconds): bool => $seconds >= 30 && $seconds <= 300))->toBeTrue()
            ->and($intervals->unique()->count())->toBeGreaterThan(1);
    }

    expect($gustSensors)->toBeGreaterThan(0)->toBeLessThan($sensors->count())
        ->and(Reading::query()->get()->every(
            fn (Reading $reading): bool => $reading->received_at->diffInSeconds($reading->observed_at, absolute: true) >= 1
                && $reading->received_at->diffInSeconds($reading->observed_at, absolute: true) <= 5,
        ))->toBeTrue();
});

test('it generates only current sensor state when history is disabled', function () {
    CarbonImmutable::setTestNow('2026-07-26 12:00:30 UTC');

    $organization = Organization::factory()->create();
    PeoplecountSensor::factory()->withOrganization($organization)->create();
    $stageSensors = StageSafetySensor::factory()->for($organization)->count(2)->create();

    $this->artisan('sensors:simulate', [
        '--type' => 'both',
        '--history' => '0',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(IntervalCount::query()->count())->toBe(1)
        ->and(Reading::query()->where('kind', ReadingKind::WindAverage)->count())->toBe($stageSensors->count())
        ->and(Reading::query()->count())->toBeBetween($stageSensors->count(), $stageSensors->count() * 2);
});

test('it does not duplicate simulation rows', function () {
    CarbonImmutable::setTestNow('2026-07-26 12:00:30 UTC');

    $organization = Organization::factory()->create();
    PeoplecountSensor::factory()->withOrganization($organization)->create();
    StageSafetySensor::factory()->for($organization)->count(2)->create();
    $arguments = [
        '--type' => 'both',
        '--history' => '1',
        '--once' => true,
    ];

    fake()->seed(1234);
    $this->artisan('sensors:simulate', $arguments)->assertExitCode(Command::SUCCESS);

    $peoplecountRows = IntervalCount::query()->count();
    $stageSafetyRows = Reading::query()->count();

    fake()->seed(1234);
    $this->artisan('sensors:simulate', $arguments)->assertExitCode(Command::SUCCESS);

    expect(IntervalCount::query()->count())->toBe($peoplecountRows)
        ->and(Reading::query()->count())->toBe($stageSafetyRows);
});

test('it uses previous-day stage state at midnight', function () {
    CarbonImmutable::setTestNow('2026-07-26 00:00:00 UTC');

    $sensor = StageSafetySensor::factory()->create();

    $this->artisan('sensors:simulate', [
        '--type' => 'stage-safety',
        '--history' => '0',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    $average = Reading::query()
        ->whereBelongsTo($sensor)
        ->where('kind', ReadingKind::WindAverage)
        ->first();

    expect($average)->not->toBeNull()
        ->and($average->observed_at->toDateString())->toBe('2026-07-25');
});

test('it catches stage-safety readings received after a live polling boundary', function () {
    CarbonImmutable::setTestNow('2026-07-26 12:00:30 UTC');

    $sensor = StageSafetySensor::factory()->create();

    $this->artisan('sensors:simulate', [
        '--type' => 'stage-safety',
        '--history' => '24',
        '--once' => true,
    ])->assertExitCode(Command::SUCCESS);

    $target = Reading::query()
        ->whereBelongsTo($sensor)
        ->where('kind', ReadingKind::WindAverage)
        ->get()
        ->first(fn (Reading $reading): bool => $reading->received_at->diffInSeconds($reading->observed_at, absolute: true) > 1);

    expect($target)->not->toBeNull();

    Reading::query()->delete();
    CarbonImmutable::setTestNow($target->observed_at->subSeconds(14));

    Sleep::fake(syncWithCarbon: true);
    $command = Artisan::all()['sensors:simulate'];
    $stop = new ReflectionMethod($command, 'stop');
    $sleeps = 0;
    Sleep::whenFakingSleep(function () use (&$sleeps, $command, $stop): void {
        $sleeps++;

        if ($sleeps === 3) {
            $stop->invoke($command, SIGINT);
        }
    });

    $this->artisan('sensors:simulate', [
        '--type' => 'stage-safety',
        '--history' => '0',
    ])->assertExitCode(Command::SUCCESS);

    expect(Reading::query()
        ->whereBelongsTo($sensor)
        ->where('kind', $target->kind)
        ->where('observed_at', $target->observed_at)
        ->exists())->toBeTrue()
        ->and($sleeps)->toBe(3);
});
