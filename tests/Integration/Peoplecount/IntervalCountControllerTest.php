<?php

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\StageSafety\Sensor as StageSafetySensor;
use App\Models\User;
use App\Services\Peoplecount\IntervalCountService;
use App\Services\Peoplecount\SensorService;

it('calls IntervalCountService with authenticated sensor and payload', function () {
    $sensor = Sensor::factory()->create();
    $payload = ['some' => 'data'];

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->withArgs(function ($argSensor, $argData) use ($sensor, $payload): bool {
            return $argSensor->id === $sensor->id && $argData === $payload;
        })
        ->andReturn(2); // Return number of processed records

    $this->app->instance(IntervalCountService::class, $mock);

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'message' => 'Interval count data processed successfully.',
            'count' => 2,
        ]);
});

it('returns 200 when no records are processed', function () {
    $sensor = Sensor::factory()->create();
    $payload = ['some' => 'data'];

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->withArgs(function ($argSensor, $argData) use ($sensor, $payload): bool {
            return $argSensor->id === $sensor->id && $argData === $payload;
        })
        ->andReturn(0); // Return 0 processed records

    $this->app->instance(IntervalCountService::class, $mock);

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'message' => 'No interval count data to process.',
        ]);
});

it('returns 400 when service throws exception', function () {
    $sensor = Sensor::factory()->create();
    $payload = ['invalid' => 'data'];

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->andThrow(new Exception('Unsupported sensor vendor: Unknown'));

    $this->app->instance(IntervalCountService::class, $mock);

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertBadRequest()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'error' => 'Processing failed',
            'message' => 'Unsupported sensor vendor: Unknown',
        ]);
});

it('returns 400 when service throws validation exception', function () {
    $sensor = Sensor::factory()->create();
    $payload = ['malformed' => 'data'];

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->andThrow(new Exception('Invalid Axis data structure: measurements must be an array.'));

    $this->app->instance(IntervalCountService::class, $mock);

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(400)
        ->assertJson([
            'error' => 'Processing failed',
            'message' => 'Invalid Axis data structure: measurements must be an array.',
        ]);
});

it('returns 401 when no authentication token provided', function () {
    $payload = ['some' => 'data'];

    $this->postJson(route('peoplecount.interval-count.store'), $payload)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'message' => 'Unauthenticated.',
        ]);
});

it('returns 401 when invalid authentication token provided', function () {
    $payload = ['some' => 'data'];

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer invalid-token',
    ])->assertStatus(401);
});

it('returns 401 when expired token provided', function () {
    $sensor = Sensor::factory()->create();
    $payload = ['some' => 'data'];

    // Create a token and then delete it to simulate expiration
    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;
    $sensor->tokens()->delete();

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(401);
});

it('rejects a Stage Safety sensor token', function () {
    $stageSafetySensor = StageSafetySensor::factory()->create();
    $token = $stageSafetySensor->createToken('stage-safety-sensor')->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), ['some' => 'data'], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();

    $this->assertDatabaseCount('peoplecount_interval_counts', 0);
});

it('rejects an authenticated web user', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('peoplecount.interval-count.store'), ['some' => 'data'])
        ->assertForbidden();

    $this->assertDatabaseCount('peoplecount_interval_counts', 0);
});

it('rejects an archived Peoplecount sensor', function () {
    $sensor = Sensor::factory()->create();
    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;
    $sensor->update(['archived_at' => now()]);

    $this->postJson(route('peoplecount.interval-count.store'), ['some' => 'data'], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();

    $this->assertDatabaseCount('peoplecount_interval_counts', 0);
});

it('processes real axis data successfully', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'AXIS-TEST-001',
        'organization_id' => $organization->id,
    ]);
    $otherSensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => $sensor->serial,
        'organization_id' => Organization::factory()->create()->id,
    ]);

    $payload = [
        'apiName' => 'Axis Retail Data',
        'apiVersion' => '0.4',
        'sensor' => [
            'serial' => 'AXIS-TEST-001',
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
                        ['direction' => 'in', 'count' => 5],
                        ['direction' => 'out', 'count' => 3],
                    ],
                ],
            ],
        ],
    ];

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(201)
        ->assertJson([
            'message' => 'Interval count data processed successfully.',
            'count' => 1,
        ]);

    // Verify data was actually stored
    $this->assertDatabaseHas('peoplecount_interval_counts', [
        'sensor_id' => $sensor->id,
        'count_in' => 5,
        'count_out' => 3,
    ]);
    $this->assertDatabaseMissing('peoplecount_interval_counts', [
        'sensor_id' => $otherSensor->id,
    ]);
});

it('processes empty axis data successfully', function () {
    $sensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'AXIS-TEST-002',
    ]);

    $payload = [
        'apiName' => 'Axis Retail Data',
        'apiVersion' => '0.4',
        'sensor' => [
            'serial' => 'AXIS-TEST-002',
        ],
        'data' => [
            'measurements' => [],
        ],
    ];

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(200)
        ->assertJson([
            'message' => 'No interval count data to process.',
        ]);

    // Verify no data was stored
    $this->assertDatabaseMissing('peoplecount_interval_counts', [
        'sensor_id' => $sensor->id,
    ]);
});

it('handles malformed axis data gracefully', function () {
    $sensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'AXIS-TEST-003',
    ]);

    $payload = [
        'apiName' => 'Wrong API',
        'apiVersion' => '0.4',
        'sensor' => [
            'serial' => 'AXIS-TEST-003',
        ],
        'data' => [
            'measurements' => [],
        ],
    ];

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(400)
        ->assertJson([
            'error' => 'Processing failed',
            'message' => 'Unsupported Axis API version or name.',
        ]);
});

it('handles sensor serial mismatch gracefully', function () {
    $sensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'AXIS-TEST-004',
    ]);
    $otherSensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'OTHER-SENSOR',
    ]);

    $payload = [
        'apiName' => 'Axis Retail Data',
        'apiVersion' => '0.4',
        'sensor' => [
            'serial' => $otherSensor->serial,
        ],
        'data' => [
            'measurements' => [],
        ],
    ];

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(400)
        ->assertJson([
            'error' => 'Processing failed',
            'message' => 'Sensor serial mismatch: expected AXIS-TEST-004, got OTHER-SENSOR',
        ]);

    $this->assertDatabaseCount('peoplecount_interval_counts', 0);
});

it('handles unsupported sensor vendor gracefully', function () {
    $sensor = Sensor::factory()->create([
        'vendor' => 'Unknown',
        'serial' => 'UNKNOWN-001',
    ]);

    $payload = ['some' => 'data'];

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(400)
        ->assertJson([
            'error' => 'Processing failed',
            'message' => 'Unsupported sensor vendor: Unknown',
        ]);
});

it('accepts empty request body', function () {
    $sensor = Sensor::factory()->create();

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->withArgs(function ($argSensor, $argData) use ($sensor): bool {
            return $argSensor->id === $sensor->id && $argData === [];
        })
        ->andReturn(0);

    $this->app->instance(IntervalCountService::class, $mock);

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(200)
        ->assertJson([
            'message' => 'No interval count data to process.',
        ]);
});

it('processes multiple measurements in single request', function () {
    $sensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'AXIS-MULTI-001',
    ]);

    $payload = [
        'apiName' => 'Axis Retail Data',
        'apiVersion' => '0.4',
        'sensor' => [
            'serial' => 'AXIS-MULTI-001',
        ],
        'data' => [
            'utcFrom' => now()->subHour()->toIso8601String(),
            'utcTo' => now()->toIso8601String(),
            'measurements' => [
                [
                    'kind' => 'people-counts',
                    'utcFrom' => now()->subHour()->toIso8601String(),
                    'utcTo' => now()->subMinutes(30)->toIso8601String(),
                    'items' => [
                        ['direction' => 'in', 'count' => 10],
                        ['direction' => 'out', 'count' => 5],
                    ],
                ],
                [
                    'kind' => 'people-counts',
                    'utcFrom' => now()->subMinutes(30)->toIso8601String(),
                    'utcTo' => now()->toIso8601String(),
                    'items' => [
                        ['direction' => 'in', 'count' => 7],
                        ['direction' => 'out', 'count' => 12],
                    ],
                ],
            ],
        ],
    ];

    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(201)
        ->assertJson([
            'message' => 'Interval count data processed successfully.',
            'count' => 2,
        ]);

    // Verify both measurements were stored
    $this->assertDatabaseCount('peoplecount_interval_counts', 2);
    $this->assertDatabaseHas('peoplecount_interval_counts', [
        'sensor_id' => $sensor->id,
        'count_in' => 10,
        'count_out' => 5,
    ]);
    $this->assertDatabaseHas('peoplecount_interval_counts', [
        'sensor_id' => $sensor->id,
        'count_in' => 7,
        'count_out' => 12,
    ]);
});

it('idempotently updates a replayed interval', function () {
    $sensor = Sensor::factory()->create([
        'vendor' => 'Axis',
        'serial' => 'AXIS-REPLAY-001',
    ]);
    $payload = [
        'apiName' => 'Axis Retail Data',
        'apiVersion' => '0.4',
        'sensor' => [
            'serial' => $sensor->serial,
        ],
        'data' => [
            'measurements' => [[
                'kind' => 'people-counts',
                'utcFrom' => '2026-07-17T11:59:00Z',
                'utcTo' => '2026-07-17T12:00:00Z',
                'items' => [
                    ['direction' => 'in', 'count' => 5],
                    ['direction' => 'out', 'count' => 3],
                ],
            ]],
        ],
    ];
    $token = $sensor->createToken(SensorService::SENSOR_TOKEN_NAME)->plainTextToken;
    $headers = ['Authorization' => 'Bearer '.$token];

    $this->postJson(route('peoplecount.interval-count.store'), $payload, $headers)
        ->assertCreated();

    $payload['data']['measurements'][0]['items'] = [
        ['direction' => 'in', 'count' => 8],
        ['direction' => 'out', 'count' => 2],
    ];

    $this->postJson(route('peoplecount.interval-count.store'), $payload, $headers)
        ->assertCreated()
        ->assertExactJson([
            'message' => 'Interval count data processed successfully.',
            'count' => 1,
        ]);

    $this->assertDatabaseCount('peoplecount_interval_counts', 1);
    $this->assertDatabaseHas('peoplecount_interval_counts', [
        'sensor_id' => $sensor->id,
        'count_in' => 8,
        'count_out' => 2,
    ]);
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.interval-count.store',
        ['auth:sanctum'],
    );
});
