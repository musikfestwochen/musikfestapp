<?php

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\IntervalCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
                        'items' => [
                            ['direction' => 'in', 'count' => 7],
                            ['direction' => 'out', 'count' => 3],
                        ],
                    ],
                ],
            ],
        ];

        $this->service->processIntervalCount($sensor, $payload);

        $this->assertDatabaseHas('peoplecount_interval_counts', [
            'sensor_id' => $sensor->id,
            'count_in' => 7,
            'count_out' => 3,
        ]);
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

    it('throws when utcFrom is missing', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => ['utcTo' => now()->toIso8601String(), 'measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required UTC timestamps in Axis data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when utcTo is missing', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => ['utcFrom' => now()->toIso8601String(), 'measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required UTC timestamps in Axis data.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when both utcFrom and utcTo are missing', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => ['measurements' => []],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required UTC timestamps in Axis data.');
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
        $this->expectExceptionMessage('Invalid Axis data structure: expected exactly one people-counts measurement.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when measurements count is not 1', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    ['kind' => 'people-counts'],
                    ['kind' => 'people-counts'],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Axis data structure: expected exactly one people-counts measurement.');
        $this->service->processIntervalCount($sensor, $data);
    });

    it('throws when measurement kind is not people-counts', function () {
        $sensor = Sensor::factory()->create(['vendor' => 'Axis', 'serial' => 'SN123']);

        $data = [
            'apiName' => 'Axis Retail Data',
            'apiVersion' => '0.4',
            'sensor' => ['serial' => 'SN123'],
            'data' => [
                'utcFrom' => now()->toIso8601String(),
                'utcTo' => now()->toIso8601String(),
                'measurements' => [
                    ['kind' => 'wrong-kind'],
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Axis data structure: expected exactly one people-counts measurement.');
        $this->service->processIntervalCount($sensor, $data);
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
