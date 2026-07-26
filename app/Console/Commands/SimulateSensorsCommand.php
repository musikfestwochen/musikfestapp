<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StageSafety\ReadingKind;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor as PeoplecountSensor;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor as StageSafetySensor;
use App\Services\Peoplecount\AreaAggregationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;

#[Signature('sensors:simulate
        {--type=both : Sensor type: peoplecount, stage-safety, both}
        {--history=3 : Hours of historical data to generate (0-24)}
        {--once : Generate one batch and exit}
    ')]
#[Description('Simulate historical and live sensor data')]
class SimulateSensorsCommand extends Command
{
    private const int BATCH_SIZE = 500;

    private const int LIVE_POLL_SECONDS = 15;

    private const array SENSOR_TYPES = [
        'both' => 'Both',
        'peoplecount' => 'People count',
        'stage-safety' => 'Stage safety',
    ];

    private const array HISTORY_OPTIONS = [
        '0' => 'None',
        '1' => '1 hour',
        '3' => '3 hours',
        '6' => '6 hours',
        '12' => '12 hours',
        '24' => '24 hours',
    ];

    private bool $running = true;

    /** @var array<int, int> */
    private array $peoplecountSensorIds = [];

    /** @var array<int, int> */
    private array $stageSafetySensorIds = [];

    /** @var array<int, int> */
    private array $stageSafetyGustSensorIds = [];

    public function handle(AreaAggregationService $areaAggregationService): int
    {
        if (app()->isProduction()) {
            error('Sensor simulation is not allowed in production.');

            return self::FAILURE;
        }

        $wizardMode = $this->input->isInteractive() && ! $this->hasExplicitOptions();
        $options = $this->resolveOptions($wizardMode);

        if (! $this->validateOptions($options['type'], $options['history'])) {
            return self::FAILURE;
        }

        if (in_array($options['type'], ['both', 'peoplecount'], true)) {
            $this->peoplecountSensorIds = PeoplecountSensor::query()
                ->whereNull('archived_at')
                ->get(['id'])
                ->map(fn (PeoplecountSensor $sensor): int => $sensor->id)
                ->all();
        }

        if (in_array($options['type'], ['both', 'stage-safety'], true)) {
            $this->stageSafetySensorIds = StageSafetySensor::query()
                ->whereNull('archived_at')
                ->get(['id'])
                ->map(fn (StageSafetySensor $sensor): int => $sensor->id)
                ->all();
            $this->stageSafetyGustSensorIds = array_values(array_filter(
                $this->stageSafetySensorIds,
                fn (): bool => fake()->boolean(),
            ));
        }

        $now = CarbonImmutable::instance(Date::now())->utc()->startOfSecond();
        $historyHours = (int) $options['history'];
        $from = $now->subHours($historyHours);
        $inserted = $this->simulate($options['type'], $from, $now, $historyHours === 0, $areaAggregationService);

        if ($inserted['peoplecount'] === 0 && in_array($options['type'], ['both', 'peoplecount'], true)) {
            $this->aggregatePeoplecount($areaAggregationService);
        }

        info(sprintf(
            'Added %d people-count intervals and %d stage-safety readings.',
            $inserted['peoplecount'],
            $inserted['stage_safety'],
        ));

        if (! $options['live']) {
            return self::SUCCESS;
        }

        info('Generating live data. Press Ctrl+C to stop.');

        $this->trap([SIGINT, SIGTERM], $this->stop(...));

        $lastRun = $now;

        while ($this->waitForNextBatch()) {
            $now = CarbonImmutable::instance(Date::now())->utc()->startOfSecond();
            $this->simulate($options['type'], $lastRun->subSeconds(5), $now, false, $areaAggregationService);
            $lastRun = $now;
        }

        outro('Sensor simulation stopped.');

        return self::SUCCESS;
    }

    /**
     * @return array{peoplecount: int, stage_safety: int}
     */
    protected function simulate(
        string $sensorType,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $currentOnly,
        AreaAggregationService $areaAggregationService,
    ): array {
        $peoplecountInserted = in_array($sensorType, ['both', 'peoplecount'], true)
            ? $this->simulatePeoplecount($from, $to, $currentOnly)
            : 0;

        if ($peoplecountInserted > 0) {
            $this->aggregatePeoplecount($areaAggregationService);
        }

        return [
            'peoplecount' => $peoplecountInserted,
            'stage_safety' => in_array($sensorType, ['both', 'stage-safety'], true)
                ? $this->simulateStageSafety($from, $to, $currentOnly)
                : 0,
        ];
    }

    protected function simulatePeoplecount(CarbonImmutable $from, CarbonImmutable $to, bool $currentOnly): int
    {
        if ($this->peoplecountSensorIds === []) {
            return 0;
        }

        $intervalStart = $currentOnly
            ? $to->startOfMinute()->subMinute()
            : $from->startOfMinute();
        $rows = [];

        while ($intervalStart->addMinute()->lte($to)) {
            $intervalEnd = $intervalStart->addMinute();

            foreach ($this->peoplecountSensorIds as $sensorId) {
                $rows[] = [
                    'sensor_id' => $sensorId,
                    'ts_from' => $intervalStart->format('Y-m-d H:i:s'),
                    'ts_to' => $intervalEnd->format('Y-m-d H:i:s'),
                    'received_at' => $to->format('Y-m-d H:i:s'),
                    'count_in' => $this->deterministicInt($sensorId.':in:'.$intervalStart->timestamp, 0, 10),
                    'count_out' => $this->deterministicInt($sensorId.':out:'.$intervalStart->timestamp, 0, 10),
                ];
            }

            $intervalStart = $intervalEnd;
        }

        $inserted = 0;

        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            $inserted += IntervalCount::query()->insertOrIgnore($batch);
        }

        return $inserted;
    }

    protected function simulateStageSafety(CarbonImmutable $from, CarbonImmutable $to, bool $currentOnly): int
    {
        $rows = [];

        foreach ($this->stageSafetySensorIds as $sensorId) {
            foreach ([ReadingKind::WindAverage, ReadingKind::WindGust] as $kind) {
                if ($kind === ReadingKind::WindGust
                    && ! in_array($sensorId, $this->stageSafetyGustSensorIds, true)) {
                    continue;
                }

                foreach ($this->stageSafetyReadingTimes($sensorId, $kind, $from, $to, $currentOnly) as $observedAt) {
                    $receivedAt = $observedAt->addSeconds(
                        $this->deterministicInt($sensorId.':'.$kind->value.':received:'.$observedAt->timestamp, 1, 5),
                    );

                    $rows[] = [
                        'sensor_id' => $sensorId,
                        'kind' => $kind->value,
                        'value' => $this->stageSafetyValue($sensorId, $kind, $observedAt),
                        'unit' => 'm/s',
                        'observed_at' => $observedAt->format('Y-m-d H:i:s'),
                        'received_at' => $receivedAt->format('Y-m-d H:i:s'),
                        'window_seconds' => 10,
                        'battery_low' => $this->deterministicInt($sensorId.':battery:'.$observedAt->timestamp, 1, 20) === 1,
                        'rssi_dbm' => $this->deterministicInt($sensorId.':rssi:'.$observedAt->timestamp, -98, -30),
                        'cv' => $this->deterministicInt($sensorId.':cv:'.$observedAt->timestamp, 55, 110),
                    ];
                }
            }
        }

        $inserted = 0;

        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            $inserted += Reading::query()->insertOrIgnore($batch);
        }

        return $inserted;
    }

    /**
     * @return list<CarbonImmutable>
     */
    protected function stageSafetyReadingTimes(
        int $sensorId,
        ReadingKind $kind,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $currentOnly,
    ): array {
        $times = [];
        $latest = null;
        $day = $currentOnly ? $from->subDay()->startOfDay() : $from->startOfDay();

        while ($day->lte($to)) {
            $observedAt = $kind === ReadingKind::WindAverage ? $day->addSeconds(15) : $day;
            $endOfDay = $day->endOfDay();

            while ($observedAt->lte($endOfDay) && $observedAt->lte($to)) {
                $receivedAt = $observedAt->addSeconds(
                    $this->deterministicInt($sensorId.':'.$kind->value.':received:'.$observedAt->timestamp, 1, 5),
                );

                if ($receivedAt->lte($to)) {
                    if ($currentOnly) {
                        $latest = $observedAt;
                    } elseif ($observedAt->gte($from)) {
                        $times[] = $observedAt;
                    }
                }

                $observedAt = $observedAt->addSeconds(
                    30 * $this->deterministicInt($sensorId.':'.$kind->value.':interval:'.$observedAt->timestamp, 1, 10),
                );
            }

            $day = $day->addDay()->startOfDay();
        }

        if ($currentOnly && $latest !== null) {
            return [$latest];
        }

        return $times;
    }

    protected function stageSafetyValue(int $sensorId, ReadingKind $kind, CarbonImmutable $observedAt): float
    {
        $period = intdiv($observedAt->getTimestamp(), 300);
        $average = $this->deterministicInt($sensorId.':wind:'.$period, 20, 1000) / 100;

        if ($kind === ReadingKind::WindAverage) {
            return $average;
        }

        return $average + ($this->deterministicInt($sensorId.':gust:'.$observedAt->timestamp, 100, 600) / 100);
    }

    protected function deterministicInt(string $seed, int $minimum, int $maximum): int
    {
        $value = (int) hexdec(substr(hash('sha256', $seed), 0, 8));

        return $minimum + ($value % ($maximum - $minimum + 1));
    }

    protected function aggregatePeoplecount(AreaAggregationService $areaAggregationService): void
    {
        Area::query()->each(
            fn (Area $area) => $areaAggregationService->updateAggregatedCounts($area),
        );
    }

    protected function waitForNextBatch(): bool
    {
        Sleep::for(self::LIVE_POLL_SECONDS)->seconds();

        return $this->running;
    }

    protected function stop(int $signal): void
    {
        $this->running = false;
    }

    /**
     * @return array{type: string, history: string, live: bool}
     */
    protected function resolveOptions(bool $wizardMode): array
    {
        if (! $wizardMode) {
            return [
                'type' => (string) $this->option('type'),
                'history' => (string) $this->option('history'),
                'live' => ! (bool) $this->option('once'),
            ];
        }

        intro('Sensor Simulation');

        return [
            'type' => (string) select(
                label: 'Sensor type',
                options: self::SENSOR_TYPES,
                default: $this->option('type'),
            ),
            'history' => (string) select(
                label: 'Historical data',
                options: self::HISTORY_OPTIONS,
                default: (string) $this->option('history'),
            ),
            'live' => confirm(
                label: 'Continue generating live data?',
                default: true,
            ),
        ];
    }

    protected function hasExplicitOptions(): bool
    {
        return $this->input->hasParameterOption(['--type', '--history', '--once']);
    }

    protected function validateOptions(string $sensorType, string $historyHours): bool
    {
        if (! isset(self::SENSOR_TYPES[$sensorType])) {
            error('Unknown sensor type: '.$sensorType.'. Available: '.implode(', ', array_keys(self::SENSOR_TYPES)));

            return false;
        }

        if (! ctype_digit($historyHours) || (int) $historyHours > 24) {
            error('History must be a whole number between 0 and 24 hours.');

            return false;
        }

        return true;
    }
}
