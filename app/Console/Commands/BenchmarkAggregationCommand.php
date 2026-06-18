<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AggregateAreaCounts;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\task;
use function Laravel\Prompts\warning;

final class BenchmarkAggregationCommand extends Command
{
    protected $signature = 'peoplecount:benchmark
        {--scenario=large : Scenario: small, medium, large, xlarge}
        {--iterations=3 : Number of iterations}
        {--db=sqlite : Database: sqlite, mariadb}
        {--mariadb=docker : MariaDB source: docker, external}
        {--output=both : Output format: json, table, both}
    ';

    protected $description = 'Benchmark peoplecount aggregation performance';

    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    private bool $listening = false;

    private bool $startedMariaDb = false;

    private ?string $temporarySqlitePath = null;

    /**
     * @var callable|null
     */
    private $queryListener = null;

    private const MARIADB_DOCKER_COMPOSE_FILE = __DIR__.'/../../../docker-compose.yml';

    private const DATABASES = [
        'sqlite' => 'SQLite',
        'mariadb' => 'MariaDB',
    ];

    private const MARIADB_SOURCES = [
        'docker' => 'Docker Compose (local)',
        'external' => 'External / already running',
    ];

    private const OUTPUT_FORMATS = [
        'json' => 'JSON file only',
        'table' => 'Summary table only',
        'both' => 'Summary table + JSON file',
    ];

    private const SCENARIOS = [
        'small' => [
            'days' => 1,
            'areas' => 1,
            'sensors_per_area' => 3,
            'granularity_minutes' => 5,
            'interval_minutes' => 5,
            'description' => '1 day, 1 area, 3 sensors',
        ],
        'medium' => [
            'days' => 3,
            'areas' => 3,
            'sensors_per_area' => 10,
            'granularity_minutes' => 5,
            'interval_minutes' => 5,
            'description' => '3 days, 3 areas, 10 sensors/area',
        ],
        'large' => [
            'days' => 7,
            'areas' => 5,
            'sensors_per_area' => 10,
            'granularity_minutes' => 5,
            'interval_minutes' => 5,
            'description' => '7 days, 5 areas, 10 sensors/area',
        ],
        'xlarge' => [
            'days' => 10,
            'areas' => 8,
            'sensors_per_area' => 10,
            'granularity_minutes' => 5,
            'interval_minutes' => 5,
            'description' => '10 days, 8 areas, 10 sensors/area',
        ],
    ];

    public function handle(): int
    {
        $wizardMode = $this->input->isInteractive() && ! $this->hasExplicitOptions();
        $options = $this->resolveOptions($wizardMode);

        if (! $this->validateOptions($options['scenario'], $options['iterations'], $options['db'], $options['mariadb'], $options['output'])) {

            return self::FAILURE;
        }

        $this->displaySettings($options);

        if ($wizardMode && $options['db'] === 'mariadb' && $options['mariadb'] === 'docker') {
            warning('MariaDB mode resets the Docker database musikfestapp_benchmark.');
        }

        $scenario = self::SCENARIOS[$options['scenario']];

        if ($options['db'] === 'mariadb' && $options['mariadb'] === 'docker') {
            $started = task(
                label: 'Starting MariaDB container',
                callback: function (Logger $logger): bool {
                    $started = $this->ensureMariaDbRunning();

                    $started
                        ? $logger->success('MariaDB is healthy')
                        : $logger->error('MariaDB did not become healthy');

                    return $started;
                },
                keepSummary: true,
            );

            if (! $started) {
                error('Could not start MariaDB container. Ensure Docker is running.');

                return self::FAILURE;
            }

            $this->startedMariaDb = true;
        }

        try {
            return $this->runBenchmark($options['scenario'], $scenario, $options['iterations'], $options['db'], $options['output']);
        } finally {
            if ($this->startedMariaDb) {
                task(
                    label: 'Stopping MariaDB container',
                    callback: function (Logger $logger): void {
                        $this->stopMariaDb();

                        $logger->success('MariaDB container stopped');
                    },
                    keepSummary: true,
                );
            }

            $this->deleteTemporarySqliteDatabase();
        }
    }

    /**
     * @return array{scenario: string, db: string, mariadb: string, iterations: int, output: string}
     */
    private function resolveOptions(bool $wizardMode): array
    {
        if (! $wizardMode) {
            return [
                'scenario' => (string) $this->option('scenario'),
                'db' => (string) $this->option('db'),
                'mariadb' => (string) $this->option('mariadb'),
                'iterations' => (int) $this->option('iterations'),
                'output' => (string) $this->option('output'),
            ];
        }

        intro('Peoplecount Aggregation Benchmark');

        $scenario = (string) select(
            label: 'Scenario',
            options: collect(self::SCENARIOS)->mapWithKeys(fn (array $v, string $k) => [$k => $v['description']])->toArray(),
            default: $this->option('scenario'),
        );
        $dbConnection = (string) select(
            label: 'Database',
            options: self::DATABASES,
            default: $this->option('db'),
        );

        return [
            'scenario' => $scenario,
            'db' => $dbConnection,
            'mariadb' => $dbConnection === 'mariadb'
                ? (string) select(
                    label: 'MariaDB source',
                    options: self::MARIADB_SOURCES,
                    default: $this->option('mariadb'),
                )
                : (string) $this->option('mariadb'),
            'iterations' => (int) number(
                label: 'Iterations',
                default: (string) $this->option('iterations'),
                min: 1,
                max: 50,
            ),
            'output' => (string) select(
                label: 'Output format',
                options: self::OUTPUT_FORMATS,
                default: $this->option('output'),
            ),
        ];
    }

    /**
     * @param  array{scenario: string, db: string, mariadb: string, iterations: int, output: string}  $options
     */
    private function displaySettings(array $options): void
    {
        $rows = [
            ['Scenario', "{$options['scenario']} — ".self::SCENARIOS[$options['scenario']]['description']],
            ['Database', self::DATABASES[$options['db']]],
        ];

        if ($options['db'] === 'mariadb') {
            $rows[] = ['MariaDB source', self::MARIADB_SOURCES[$options['mariadb']]];
        }

        $rows[] = ['Iterations', (string) $options['iterations']];
        $rows[] = ['Output', self::OUTPUT_FORMATS[$options['output']]];

        table(
            headers: ['Setting', 'Value'],
            rows: $rows,
        );
    }

    private function hasExplicitOptions(): bool
    {
        return $this->input->hasParameterOption(['--scenario', '--iterations', '--db', '--mariadb', '--output']);
    }

    private function validateOptions(string $scenarioName, int $iterations, string $dbConnection, string $mariadbSource, string $outputFormat): bool
    {
        if (! isset(self::SCENARIOS[$scenarioName])) {
            error("Unknown scenario: {$scenarioName}. Available: ".implode(', ', array_keys(self::SCENARIOS)));

            return false;
        }

        if (! isset(self::DATABASES[$dbConnection])) {
            error('Unknown database: '.$dbConnection.'. Available: '.implode(', ', array_keys(self::DATABASES)));

            return false;
        }

        if (! isset(self::MARIADB_SOURCES[$mariadbSource])) {
            error('Unknown MariaDB source: '.$mariadbSource.'. Available: '.implode(', ', array_keys(self::MARIADB_SOURCES)));

            return false;
        }

        if (! isset(self::OUTPUT_FORMATS[$outputFormat])) {
            error('Unknown output format: '.$outputFormat.'. Available: '.implode(', ', array_keys(self::OUTPUT_FORMATS)));

            return false;
        }

        if ($iterations < 1 || $iterations > 50) {
            error('Iterations must be between 1 and 50.');

            return false;
        }

        return true;
    }

    private function runBenchmark(string $scenarioName, array $scenario, int $iterations, string $dbConnection, string $outputFormat): int
    {
        config(['database.default' => $dbConnection]);
        config(['peoplecount.aggregation.granularity_minutes' => $scenario['granularity_minutes']]);

        if ($dbConnection === 'mariadb') {
            config([
                'database.connections.mariadb.host' => '127.0.0.1',
                'database.connections.mariadb.port' => '3306',
                'database.connections.mariadb.database' => 'musikfestapp_benchmark',
                'database.connections.mariadb.username' => 'root',
                'database.connections.mariadb.password' => 'password',
            ]);
            DB::purge('mariadb');
        } else {
            config(['database.connections.sqlite.database' => $this->createTemporarySqliteDatabase()]);
            DB::purge('sqlite');
        }

        $migrated = task(
            label: 'Running migrate:fresh',
            callback: function (Logger $logger): int {
                $exitCode = $this->callSilent('migrate:fresh', ['--force' => true]);

                $exitCode === 0
                    ? $logger->success('Database migrated')
                    : $logger->error('Migration failed');

                return $exitCode;
            },
            keepSummary: true,
        );

        if ($migrated !== 0) {
            error('Migration failed. Check your database configuration.');

            return self::FAILURE;
        }

        $seedMetrics = task(
            label: 'Seeding benchmark data',
            callback: function (Logger $logger) use ($scenario): array {
                $metrics = $this->measureSeeder($scenario);

                $logger->success('Seeded '.number_format($metrics['interval_counts_total']).' interval counts');

                return $metrics;
            },
            keepSummary: true,
        );

        task(
            label: 'Running warmup iteration',
            callback: function (Logger $logger): void {
                $this->clearAggregatedCounts();
                $this->runAggregation();

                $logger->success('Warmup complete');
            },
            keepSummary: true,
        );

        $measurements = [];

        $progressBar = progress(label: 'Running iterations', steps: $iterations);
        $progressBar->start();

        for ($i = 1; $i <= $iterations; $i++) {
            $this->clearAggregatedCounts();
            $result = $this->measureAggregation();
            $result['scenario'] = $scenarioName;
            $result['phase'] = $i;
            $result['algorithm'] = 'current';
            $result['is_correct'] = true;
            $measurements[] = $result;
            $progressBar->advance();
        }

        $progressBar->finish();

        $output = [
            'generated_at' => now()->toIso8601String(),
            'scenario' => $scenarioName,
            'scenario_config' => $scenario,
            'db_connection' => $dbConnection,
            'iterations' => $iterations,
            'seed_metrics' => $seedMetrics,
            'measurements' => $measurements,
            'summary' => $this->summarize($measurements),
        ];

        $path = null;

        if ($outputFormat !== 'table') {
            $path = $this->writeOutput($output);

            info("Results saved to {$path}");
        }

        if ($outputFormat !== 'json') {
            $this->printSummaryTable($output);
        }

        outro($path === null ? 'Benchmark complete' : "Benchmark complete — results saved to {$path}");

        return self::SUCCESS;
    }

    private function seedScenarioData(array $scenario): array
    {
        $eventStart = Carbon::parse('2026-01-01 00:00:00')->utc();
        $eventEnd = $eventStart->copy()->addDays($scenario['days']);

        $organization = Organization::factory()->create(['name' => 'Benchmark Org']);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'name' => "Benchmark {$scenario['days']}d Event",
            'starts_at' => $eventStart,
            'ends_at' => $eventEnd,
        ]);

        $areas = [];
        $sensors = [];
        $assignments = [];
        $totalIntervals = 0;

        for ($areaIdx = 0; $areaIdx < $scenario['areas']; $areaIdx++) {
            $area = Area::factory()->create([
                'event_id' => $event->id,
                'name' => 'Area '.chr(65 + $areaIdx),
            ]);
            $areas[] = $area;

            for ($sensorIdx = 0; $sensorIdx < $scenario['sensors_per_area']; $sensorIdx++) {
                $sensor = Sensor::factory()->create([
                    'organization_id' => $organization->id,
                ]);
                $sensors[] = $sensor;

                $assignment = Assignment::factory()->create([
                    'event_id' => $event->id,
                    'area_id' => $area->id,
                    'sensor_id' => $sensor->id,
                    'direction_flipped' => $sensorIdx % 5 === 4,
                    'active_from' => $eventStart,
                    'active_to' => $eventEnd,
                ]);
                $assignments[] = $assignment;

                $count = $this->seedIntervalCounts($sensor, $eventStart, $eventEnd, $scenario['interval_minutes']);
                $totalIntervals += $count;
            }
        }

        return [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'areas_count' => count($areas),
            'sensors_count' => count($sensors),
            'assignments_count' => count($assignments),
            'interval_counts_total' => $totalIntervals,
            'event_days' => $scenario['days'],
            'granularity_minutes' => $scenario['granularity_minutes'],
        ];
    }

    private function seedIntervalCounts(Sensor $sensor, Carbon $start, Carbon $end, int $intervalMinutes): int
    {
        $current = $start->copy();
        $batch = [];
        $count = 0;
        $batchSize = 500;

        while ($current < $end) {
            $tsTo = $current->copy()->addMinutes($intervalMinutes);
            $batch[] = [
                'sensor_id' => $sensor->id,
                'ts_from' => $current->format('Y-m-d H:i:s'),
                'ts_to' => $tsTo->format('Y-m-d H:i:s'),
                'count_in' => random_int(0, 25),
                'count_out' => random_int(0, 15),
                'received_at' => $tsTo->format('Y-m-d H:i:s'),
            ];
            $count++;

            if (count($batch) >= $batchSize) {
                IntervalCount::query()->insert($batch);
                $batch = [];
            }

            $current = $tsTo;
        }

        if ($batch !== []) {
            IntervalCount::query()->insert($batch);
        }

        return $count;
    }

    private function measureSeeder(array $scenario): array
    {
        memory_reset_peak_usage();

        $startTime = hrtime(true);
        $startMemory = memory_get_peak_usage(true);

        $seedData = $this->seedScenarioData($scenario);

        $wallTimeMs = (hrtime(true) - $startTime) / 1e6;
        $peakMemoryMib = (memory_get_peak_usage(true) - $startMemory) / (1024 * 1024);

        return [
            'wall_time_ms' => round($wallTimeMs, 1),
            'peak_memory_mib' => max(round($peakMemoryMib, 1), 0),
            'areas_count' => $seedData['areas_count'],
            'sensors_count' => $seedData['sensors_count'],
            'assignments_count' => $seedData['assignments_count'],
            'interval_counts_total' => $seedData['interval_counts_total'],
            'event_days' => $seedData['event_days'],
            'granularity_minutes' => $seedData['granularity_minutes'],
        ];
    }

    private function measureAggregation(): array
    {
        $this->startQueryListener();
        memory_reset_peak_usage();

        $startMemory = memory_get_usage(true);
        $startTime = hrtime(true);

        $this->runAggregation();

        $wallTimeMs = (hrtime(true) - $startTime) / 1e6;
        $peakMemoryMib = memory_get_peak_usage(true) / (1024 * 1024);
        $memoryDeltaMib = (memory_get_peak_usage(true) - $startMemory) / (1024 * 1024);

        $this->stopQueryListener();

        return [
            'wall_time_ms' => [
                'mean' => round($wallTimeMs, 3),
                'p5' => round($wallTimeMs, 3),
                'p95' => round($wallTimeMs, 3),
                'iterations' => 1,
            ],
            'query_count' => [
                'mean' => $this->queryCount,
                'p5' => $this->queryCount,
                'p95' => $this->queryCount,
                'iterations' => 1,
            ],
            'query_time_ms' => [
                'mean' => round($this->queryTimeMs, 3),
                'p5' => round($this->queryTimeMs, 3),
                'p95' => round($this->queryTimeMs, 3),
                'iterations' => 1,
            ],
            'peak_memory_mib' => [
                'mean' => round($peakMemoryMib, 1),
                'p5' => round($peakMemoryMib, 1),
                'p95' => round($peakMemoryMib, 1),
                'iterations' => 1,
            ],
            'memory_delta_mib' => round($memoryDeltaMib, 1),
        ];
    }

    private function startQueryListener(): void
    {
        $this->queryCount = 0;
        $this->queryTimeMs = 0.0;
        $this->listening = true;

        if ($this->queryListener === null) {
            $this->queryListener = function ($query) {
                if (! $this->listening) {
                    return;
                }
                $this->queryCount++;
                $this->queryTimeMs += $query->time;
            };
            DB::listen($this->queryListener);
        }
    }

    private function stopQueryListener(): void
    {
        $this->listening = false;
    }

    private function runAggregation(): void
    {
        AggregateAreaCounts::dispatchSync();
    }

    private function clearAggregatedCounts(): void
    {
        AreaAggregatedCount::query()->delete();
    }

    private function summarize(array $measurements): array
    {
        if ($measurements === []) {
            return [];
        }

        $wallTimes = array_column(array_column($measurements, 'wall_time_ms'), 'mean');
        $queryCounts = array_column(array_column($measurements, 'query_count'), 'mean');
        $queryTimes = array_column(array_column($measurements, 'query_time_ms'), 'mean');
        $memories = array_column(array_column($measurements, 'peak_memory_mib'), 'mean');

        sort($wallTimes);
        sort($queryCounts);
        sort($queryTimes);
        sort($memories);

        $count = count($wallTimes);
        $p5 = fn (array $arr): float => $arr[(int) floor($count * 0.05)] ?? $arr[0];
        $p95 = fn (array $arr): float => $arr[(int) floor($count * 0.95)] ?? $arr[$count - 1];
        $mean = fn (array $arr): float => array_sum($arr) / count($arr);

        return [
            'wall_time_ms' => [
                'mean' => round($mean($wallTimes), 1),
                'p5' => round($p5($wallTimes), 1),
                'p95' => round($p95($wallTimes), 1),
                'min' => round($wallTimes[0], 1),
                'max' => round($wallTimes[$count - 1], 1),
            ],
            'query_count' => [
                'mean' => round($mean($queryCounts), 1),
                'p5' => round($p5($queryCounts), 1),
                'p95' => round($p95($queryCounts), 1),
                'min' => round($queryCounts[0], 1),
                'max' => round($queryCounts[$count - 1], 1),
            ],
            'query_time_ms' => [
                'mean' => round($mean($queryTimes), 1),
                'p5' => round($p5($queryTimes), 1),
                'p95' => round($p95($queryTimes), 1),
                'min' => round($queryTimes[0], 1),
                'max' => round($queryTimes[$count - 1], 1),
            ],
            'peak_memory_mib' => [
                'mean' => round($mean($memories), 1),
                'p5' => round($p5($memories), 1),
                'p95' => round($p95($memories), 1),
                'min' => round($memories[0], 1),
                'max' => round($memories[$count - 1], 1),
            ],
            'iterations' => $count,
        ];
    }

    private function writeOutput(array $output): string
    {
        $timestamp = now()->format('Ymd-His');
        $filename = "peoplecount-benchmark-{$timestamp}.json";
        $path = storage_path("app/benchmarks/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function createTemporarySqliteDatabase(): string
    {
        $directory = storage_path('app/benchmarks');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->temporarySqlitePath = $directory.'/peoplecount-benchmark-'.bin2hex(random_bytes(8)).'.sqlite';

        file_put_contents($this->temporarySqlitePath, '');

        return $this->temporarySqlitePath;
    }

    private function deleteTemporarySqliteDatabase(): void
    {
        if ($this->temporarySqlitePath === null) {
            return;
        }

        DB::disconnect('sqlite');

        if (is_file($this->temporarySqlitePath)) {
            unlink($this->temporarySqlitePath);
        }

        $this->temporarySqlitePath = null;
    }

    private function printSummaryTable(array $output): void
    {
        $summary = $output['summary'];
        $seed = $output['seed_metrics'];

        table(
            headers: ['Metric', 'Value'],
            rows: [
                ['Scenario', $output['scenario']],
                ['DB Connection', $output['db_connection']],
                ['Days', $seed['event_days']],
                ['Areas', $seed['areas_count']],
                ['Sensors', $seed['sensors_count']],
                ['Assignments', $seed['assignments_count']],
                ['Interval Counts', number_format($seed['interval_counts_total'])],
                ['Granularity (min)', $seed['granularity_minutes']],
                ['Seed Time (ms)', $seed['wall_time_ms']],
                ['---', '---'],
                ['Wall Time Mean (ms)', $summary['wall_time_ms']['mean'] ?? '-'],
                ['Wall Time P95 (ms)', $summary['wall_time_ms']['p95'] ?? '-'],
                ['Query Count Mean', $summary['query_count']['mean'] ?? '-'],
                ['Query Time Mean (ms)', $summary['query_time_ms']['mean'] ?? '-'],
                ['Peak Memory (MiB)', $summary['peak_memory_mib']['mean'] ?? '-'],
            ],
        );
    }

    private function ensureMariaDbRunning(): bool
    {
        $startResult = Process::run(['docker', 'compose', '-f', self::MARIADB_DOCKER_COMPOSE_FILE, 'up', '-d', 'mariadb']);

        if (! $startResult->successful()) {
            return false;
        }

        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $healthResult = Process::run(['docker', 'compose', '-f', self::MARIADB_DOCKER_COMPOSE_FILE, 'exec', '-T', 'mariadb', 'healthcheck.sh', '--connect', '--innodb_initialized']);

            if ($healthResult->successful()) {
                return true;
            }

            $attempt++;
            sleep(1);
        }

        return false;
    }

    private function stopMariaDb(): void
    {
        Process::run(['docker', 'compose', '-f', self::MARIADB_DOCKER_COMPOSE_FILE, 'stop', 'mariadb']);
    }
}
