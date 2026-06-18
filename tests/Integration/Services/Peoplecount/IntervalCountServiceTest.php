<?php

use App\Models\Organization;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\IntervalCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

covers(IntervalCountService::class);

beforeEach(function () {
    $this->service = new IntervalCountService;
});

describe('processIntervalCount', function () {
    it('stores interval count for valid Axis payload', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create([
            'vendor' => 'Axis',
            'serial' => 'AXIS-001',
        ]);

        $payload = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => [
                'serial' => 'AXIS-001',
            ],
            'data' => [
                'utcFrom' => now()->subMinute()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => now()->subMinute()->toIso8601String(),
                        'utcTo' => now()->toIso8601String(),
                        'items' => [
                            ['direction' => 'in', 'count' => 7],
                            ['direction' => 'out', 'count' => 3],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->processIntervalCount($sensor, $payload);

        expect($result)->toBe(1);
        $this->assertDatabaseHas('peoplecount_interval_counts', [
            'sensor_id' => $sensor->id,
            'count_in' => 7,
            'count_out' => 3,
        ]);
    });

    it('stores received_at timestamp when interval arrives', function () {
        $timezone = (string) config('app.timezone');

        Carbon::setTestNow(Carbon::parse('2026-01-01 10:15:00', $timezone));

        try {
            $org = Organization::factory()->create();
            $sensor = Sensor::factory()->withOrganization($org)->create([
                'vendor' => 'Axis',
                'serial' => 'AXIS-002',
            ]);

            $payload = [
                'apiName' => 'Axis Retail Data',
                'apiVersion' => '0.4',
                'sensor' => [
                    'serial' => 'AXIS-002',
                ],
                'data' => [
                    'utcFrom' => now()->subMinute()->toIso8601String(),
                    'utcTo' => now()->toIso8601String(),
                    'measurements' => [
                        [
                            'kind' => 'people-counts',
                            'utcFrom' => now()->subMinute()->toIso8601String(),
                            'utcTo' => now()->toIso8601String(),
                            'items' => [
                                ['direction' => 'in', 'count' => 2],
                                ['direction' => 'out', 'count' => 1],
                            ],
                        ],
                    ],
                ],
            ];

            $this->service->processIntervalCount($sensor, $payload);

            $intervalCount = IntervalCount::query()->first();

            expect($intervalCount)->not->toBeNull()
                ->and($intervalCount->received_at)->not->toBeNull()
                ->and($intervalCount->received_at->toIso8601String())->toBe('2026-01-01T10:15:00+00:00');
        } finally {
            Carbon::setTestNow();
        }
    });

    it('upserts duplicate sensor intervals with latest received values', function () {
        $timezone = (string) config('app.timezone');
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create([
            'vendor' => 'Axis',
            'serial' => 'AXIS-UPSERT-001',
        ]);

        $payload = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => [
                'serial' => 'AXIS-UPSERT-001',
            ],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => '2026-01-01T10:00:00+00:00',
                        'utcTo' => '2026-01-01T10:01:00+00:00',
                        'items' => [
                            ['direction' => 'in', 'count' => 5],
                            ['direction' => 'out', 'count' => 1],
                        ],
                    ],
                ],
            ],
        ];

        try {
            Carbon::setTestNow(Carbon::parse('2026-01-01 10:02:00', $timezone));
            $this->service->processIntervalCount($sensor, $payload);

            $payload['data']['measurements'][0]['items'] = [
                ['direction' => 'in', 'count' => 9],
                ['direction' => 'out', 'count' => 3],
            ];

            Carbon::setTestNow(Carbon::parse('2026-01-01 10:03:00', $timezone));
            $result = $this->service->processIntervalCount($sensor, $payload);
        } finally {
            Carbon::setTestNow();
        }

        $intervalCount = IntervalCount::query()->sole();

        expect($result)->toBe(1)
            ->and($intervalCount->count_in)->toBe(9)
            ->and($intervalCount->count_out)->toBe(3)
            ->and($intervalCount->received_at->toIso8601String())->toBe('2026-01-01T10:03:00+00:00');
    });

    it('logs warning when interval arrives more than one minute late', function () {
        $timezone = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:15:00', $timezone));
        Log::spy();

        try {
            $org = Organization::factory()->create();
            $sensor = Sensor::factory()->withOrganization($org)->create([
                'vendor' => 'Axis',
                'serial' => 'AXIS-LATE-001',
            ]);

            $payload = [
                'apiName' => 'Axis Retail Data',
                'apiVersion' => '0.4',
                'sensor' => [
                    'serial' => 'AXIS-LATE-001',
                ],
                'data' => [
                    'measurements' => [
                        [
                            'kind' => 'people-counts',
                            'utcFrom' => '2026-01-01T10:10:00+00:00',
                            'utcTo' => '2026-01-01T10:13:00+00:00',
                            'items' => [
                                ['direction' => 'in', 'count' => 4],
                                ['direction' => 'out', 'count' => 1],
                            ],
                        ],
                    ],
                ],
            ];

            $this->service->processIntervalCount($sensor, $payload);

            Log::shouldHaveReceived('warning')->once();
        } finally {
            Carbon::setTestNow();
        }
    });

    it('throws on unsupported vendor with correct message content', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Unknown']);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported sensor vendor: Unknown');
        $this->service->processIntervalCount($sensor, []);
    });

    it('throws on Axis API name mismatch', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Wrong API Name',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => ['utcFrom' => now()->toIso8601String(), 'utcTo' => now()->toIso8601String(), 'measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported Axis API version or name.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws on Axis API version mismatch', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.3',
            'sensor' => ['serial' => 'SN123'],
            'data' => ['utcFrom' => now()->toIso8601String(), 'utcTo' => now()->toIso8601String(), 'measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported Axis API version or name.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws on sensor serial mismatch with exact message', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'ABC123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'XYZ999'],
            'data' => ['utcFrom' => now()->toIso8601String(), 'utcTo' => now()->toIso8601String(), 'measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Sensor serial mismatch: expected ABC123, got XYZ999');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws on missing sensor serial with exact message', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'ABC123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => [],
            'data' => ['utcFrom' => now()->toIso8601String(), 'utcTo' => now()->toIso8601String(), 'measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Sensor serial mismatch: expected ABC123, got missing');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('returns 0 for empty measurements array', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => ['measurements' => []],
        ];

        $result = $this->service->processIntervalCount($sensor, $data);
        expect($result)->toBe(0);
    });

    it('throws when utcFrom is missing at measurement level', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcTo' => now()->toIso8601String(),
                        'items' => [],
                    ],
                ],
            ],
        ];

        // This should fail because measurement-level utcFrom is missing
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required UTC timestamps in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when utcTo is missing at measurement level', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => now()->toIso8601String(),
                        'items' => [],
                    ],
                ],
            ],
        ];

        // This should fail because measurement-level utcTo is missing
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required UTC timestamps in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when both utcFrom and utcTo are missing at measurement level', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'items' => [],
                    ],
                ],
            ],
        ];

        // This should fail because measurement-level timestamps are missing
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required UTC timestamps in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when measurements is not an array', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => 'not an array',
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Axis data structure: measurements must be an array.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('processes multiple people-counts measurements', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create([
            'vendor' => 'Axis',
            'serial' => 'SN123',
        ]);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => now()->subMinutes(2)->toIso8601String(),
                        'utcTo' => now()->subMinute()->toIso8601String(),
                        'items' => [
                            ['direction' => 'in', 'count' => 5],
                            ['direction' => 'out', 'count' => 2],
                        ],
                    ],
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => now()->subMinute()->toIso8601String(),
                        'utcTo' => now()->toIso8601String(),
                        'items' => [
                            ['direction' => 'in', 'count' => 3],
                            ['direction' => 'out', 'count' => 1],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->processIntervalCount($sensor, $data);
        expect($result)->toBe(2);

        $this->assertDatabaseCount('peoplecount_interval_counts', 2);

        $receivedAts = IntervalCount::query()->pluck('received_at')->unique();
        expect($receivedAts)->toHaveCount(1);
    });

    it('throws when UTC timestamps do not include timezone offset', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => '2026-01-01 10:00:00',
                        'utcTo' => '2026-01-01 10:01:00',
                        'items' => [
                            ['direction' => 'in', 'count' => 1],
                            ['direction' => 'out', 'count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid utcFrom timestamp in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when utcFrom is empty string', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => '',
                        'utcTo' => '2026-01-01T10:01:00+00:00',
                        'items' => [
                            ['direction' => 'in', 'count' => 1],
                            ['direction' => 'out', 'count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid utcFrom timestamp in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when utcFrom has timezone suffix but invalid date format', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => 'not-a-dateZ',
                        'utcTo' => '2026-01-01T10:01:00+00:00',
                        'items' => [
                            ['direction' => 'in', 'count' => 1],
                            ['direction' => 'out', 'count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid utcFrom timestamp in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when utcFrom is whitespace-only string', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => '   ',
                        'utcTo' => '2026-01-01T10:01:00+00:00',
                        'items' => [
                            ['direction' => 'in', 'count' => 1],
                            ['direction' => 'out', 'count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid utcFrom timestamp in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when utcFrom is not a string', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => 12345,
                        'utcTo' => '2026-01-01T10:01:00+00:00',
                        'items' => [
                            ['direction' => 'in', 'count' => 1],
                            ['direction' => 'out', 'count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid utcFrom timestamp in measurement data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('accepts utcFrom with surrounding whitespace when timezone suffix is valid', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'measurements' => [
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => ' 2026-01-01T10:00:00+00:00   ',
                        'utcTo' => ' 2026-01-01T10:01:00+00:00   ',
                        'items' => [
                            ['direction' => 'in', 'count' => 1],
                            ['direction' => 'out', 'count' => 0],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->processIntervalCount($sensor, $data);

        expect($result)->toBe(1);
        $this->assertDatabaseHas('peoplecount_interval_counts', [
            'sensor_id' => $sensor->id,
            'count_in' => 1,
            'count_out' => 0,
        ]);
    });

    it('skips non-people-counts measurements and processes only people-counts', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create([
            'vendor' => 'Axis',
            'serial' => 'SN123',
        ]);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    ['kind' => 'temperature'],
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => now()->subMinutes(2)->toIso8601String(),
                        'utcTo' => now()->subMinute()->toIso8601String(),
                        'items' => [
                            ['direction' => 'in', 'count' => 5],
                            ['direction' => 'out', 'count' => 2],
                        ],
                    ],
                    ['kind' => 'humidity'],
                    [
                        'kind' => 'people-counts',
                        'utcFrom' => now()->subMinute()->toIso8601String(),
                        'utcTo' => now()->toIso8601String(),
                        'items' => [
                            ['direction' => 'in', 'count' => 3],
                            ['direction' => 'out', 'count' => 1],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->processIntervalCount($sensor, $data);
        expect($result)->toBe(2);

        $this->assertDatabaseCount('peoplecount_interval_counts', 2);
    });

    it('returns 0 when no people-counts measurements found', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    ['kind' => 'temperature'],
                    ['kind' => 'humidity'],
                ],
            ],
        ];

        $result = $this->service->processIntervalCount($sensor, $data);
        expect($result)->toBe(0);

        $this->assertDatabaseCount('peoplecount_interval_counts', 0);
    });
});

describe('extractCountsFromItems', function () {
    beforeEach(function () {
        $this->service = new IntervalCountService;
    });

    function callExtractCountsFromItems($service, $items): mixed
    {
        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('extractCountsFromItems');
        $method->setAccessible(true);

        return $method->invoke($service, $items);
    }

    it('returns both counts when both directions are present', function () {
        $items = [
            ['direction' => 'in', 'count' => 5],
            ['direction' => 'out', 'count' => 2],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 5, 'countOut' => 2]);
    });

    it('returns 0 for missing directions', function () {
        $items = [
            ['direction' => 'in', 'count' => 7],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 7, 'countOut' => 0]);

        $items = [
            ['direction' => 'out', 'count' => 3],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 3]);
    });

    it('returns 0 for empty items', function () {
        $result = callExtractCountsFromItems($this->service, []);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('handles missing count or direction keys', function () {
        $items = [
            ['direction' => 'in'],
            ['count' => 4],
            [],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('handles duplicate directions (last wins)', function () {
        $items = [
            ['direction' => 'in', 'count' => 1],
            ['direction' => 'in', 'count' => 9],
            ['direction' => 'out', 'count' => 2],
            ['direction' => 'out', 'count' => 8],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 9, 'countOut' => 8]);
    });

    it('handles zero and negative counts', function () {
        $items = [
            ['direction' => 'in', 'count' => 0],
            ['direction' => 'out', 'count' => -3],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => -3]);
    });

    it('ignores unknown directions', function () {
        $items = [
            ['direction' => 'sideways', 'count' => 99],
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('handles missing direction key with default empty string', function () {
        $items = [
            ['count' => 5], // missing direction key, should default to empty string
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('handles null direction with default empty string', function () {
        $items = [
            ['direction' => null, 'count' => 5], // null direction, should default to empty string
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('handles missing count key with default 0', function () {
        $items = [
            ['direction' => 'in'], // missing count key, should default to 0
            ['direction' => 'out'], // missing count key, should default to 0
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('handles null count with default 0', function () {
        $items = [
            ['direction' => 'in', 'count' => null], // null count, should default to 0
            ['direction' => 'out', 'count' => null], // null count, should default to 0
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result)->toBe(['countIn' => 0, 'countOut' => 0]);
    });

    it('verifies default count values are 0 not 1 or -1', function () {
        $items = [
            ['direction' => 'in'], // should default to 0, not 1 or -1
            ['direction' => 'out'], // should default to 0, not 1 or -1
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result['countIn'])->toBe(0);
        expect($result['countOut'])->toBe(0);
        expect($result['countIn'])->not->toBe(1);
        expect($result['countIn'])->not->toBe(-1);
        expect($result['countOut'])->not->toBe(1);
        expect($result['countOut'])->not->toBe(-1);
    });

    it('verifies null direction defaults to empty string and does not match in', function () {
        $items = [
            ['direction' => null, 'count' => 10], // null should default to '', not match 'in'
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result['countIn'])->toBe(0); // Should be 0 because null ?? '' !== 'in'
        expect($result['countOut'])->toBe(0);
    });

    it('verifies null direction defaults to empty string and does not match out', function () {
        $items = [
            ['direction' => null, 'count' => 15], // null should default to '', not match 'out'
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result['countIn'])->toBe(0);
        expect($result['countOut'])->toBe(0); // Should be 0 because null ?? '' !== 'out'
    });

    it('verifies missing direction defaults to empty string and does not match in', function () {
        $items = [
            ['count' => 20], // missing direction should default to '', not match 'in'
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result['countIn'])->toBe(0); // Should be 0 because (missing) ?? '' !== 'in'
        expect($result['countOut'])->toBe(0);
    });

    it('verifies missing direction defaults to empty string and does not match out', function () {
        $items = [
            ['count' => 25], // missing direction should default to '', not match 'out'
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result['countIn'])->toBe(0);
        expect($result['countOut'])->toBe(0); // Should be 0 because (missing) ?? '' !== 'out'
    });

    it('verifies empty string direction does not match in or out', function () {
        $items = [
            ['direction' => '', 'count' => 30], // empty string should not match 'in' or 'out'
        ];
        $result = callExtractCountsFromItems($this->service, $items);
        expect($result['countIn'])->toBe(0); // Should be 0 because '' !== 'in'
        expect($result['countOut'])->toBe(0); // Should be 0 because '' !== 'out'
    });
});
