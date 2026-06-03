<?php

use App\Models\Organization;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

covers(SensorService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new SensorService;
});

describe('getSensors', function () {
    it('returns sensors', function () {
        $org = Organization::factory()->create();
        Sensor::factory()->count(15)->withOrganization($org)->create();
        setPermissionsOrgId($org->id);

        $result = $this->service->getSensors();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(15);
    });

    it('filters sensors by organization', function () {
        $org = Organization::factory()->create();
        Sensor::factory()->count(13)->withOrganization($org)->create();
        setPermissionsOrgId($org->id);
        $foreignOrg = Organization::factory()->create();
        Sensor::factory()->count(21)->withOrganization($foreignOrg)->create();

        $result = $this->service->getSensors();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(13);
    });

    it('returns empty collection when no users exist', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);

        $result = $this->service->getSensors();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('filters sensors by organization when no sensors', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id);
        $foreignOrg = Organization::factory()->create();
        Sensor::factory()->count(21)->withOrganization($foreignOrg)->create();

        $result = $this->service->getSensors();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });
});

describe('createWithToken', function () {
    it('creates a sensor and generates a token', function () {
        $org = Organization::factory()->create();
        $attributes = [
            'organization_id' => $org->id,
            'vendor' => 'TestVendor',
            'model' => 'TestModel',
            'serial' => 'SN123456',
        ];

        $sensor = $this->service->createWithToken($attributes);

        expect($sensor)->toBeInstanceOf(Sensor::class)
            ->and($sensor->organization_id)->toBe($org->id)
            ->and($sensor->vendor)->toBe('TestVendor')
            ->and($sensor->model)->toBe('TestModel')
            ->and($sensor->serial)->toBe('SN123456')
            ->and($sensor->api_token)->not->toBeEmpty();

        // Token should exist in the database
        $dbToken = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->first();
        expect($dbToken)->not->toBeNull();

        // Ensure api_token is persisted in the database
        $sensorFromDb = Sensor::query()->find($sensor->id);
        expect($sensorFromDb->api_token)->toBe($sensor->api_token);
    });

    it('creates sensor with token that matches the token in database', function () {
        $org = Organization::factory()->create();
        $attributes = [
            'organization_id' => $org->id,
            'vendor' => 'TestVendor',
            'model' => 'TestModel',
            'serial' => 'SN123456',
        ];

        $sensor = $this->service->createWithToken($attributes);

        // Get the actual token from the database
        $dbToken = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->first();

        // The api_token field should contain the token part (after the |)
        expect($sensor->api_token)->toBeString()
            ->and($sensor->api_token)->not->toContain('|')
            ->and(strlen($sensor->api_token))->toBeGreaterThan(10);
    });

    it('saves api_token to database correctly', function () {
        $org = Organization::factory()->create();
        $attributes = [
            'organization_id' => $org->id,
            'vendor' => 'TestVendor',
            'model' => 'TestModel',
            'serial' => 'SN123456',
        ];

        $sensor = $this->service->createWithToken($attributes);

        // Refresh from database to ensure it was persisted
        $sensor->refresh();

        expect($sensor->api_token)->not->toBeNull()
            ->and($sensor->api_token)->not->toBeEmpty()
            ->and($sensor->api_token)->toBeString();
    });
});

describe('createOrRegenerateToken', function () {
    it('creates a new token for a sensor', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        $token = $this->service->createOrRegenerateToken($sensor);

        expect($token)->not->toBeEmpty();

        $dbToken = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->first();
        expect($dbToken)->not->toBeNull();
    });

    it('regenerates token and deletes previous one', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        $firstToken = $this->service->createOrRegenerateToken($sensor);

        $dbToken1 = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->first();
        expect($dbToken1)->not->toBeNull();

        $secondToken = $this->service->createOrRegenerateToken($sensor);

        // Should have only one token with the name
        $tokensCount = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->count();
        expect($tokensCount)->toBe(1)
            ->and($secondToken)->not->toBe($firstToken);
    });

    it('deletes multiple existing tokens with same name', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Create multiple tokens manually with the same name
        $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
        $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
        $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);

        expect($sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->count())->toBe(3);

        $newToken = $this->service->createOrRegenerateToken($sensor);

        // Should now have only one token with the name
        $tokensCount = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->count();
        expect($tokensCount)->toBe(1)
            ->and($newToken)->not->toBeEmpty();
    });

    it('returns only the token part when token has pipe format', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        $token = $this->service->createOrRegenerateToken($sensor);

        // Token should not contain pipe character (should be the token part only)
        expect($token)->not->toContain('|')
            ->and($token)->toBeString()
            ->and(strlen($token))->toBeGreaterThan(10);
    });

    it('handles token without pipe format gracefully', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        $token = $this->service->createOrRegenerateToken($sensor);

        // Should return a valid token regardless of format
        expect($token)->toBeString()
            ->and($token)->not->toBeEmpty();
    });

    it('uses correct token name constant', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        $this->service->createOrRegenerateToken($sensor);

        $dbToken = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->first();
        expect($dbToken->name)->toBe('peoplecount_sensor_token');
    });

    it('extracts token correctly when token contains multiple pipe characters', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        // Mock createToken to return a token with multiple pipe characters
        $mockToken = new class
        {
            public $plainTextToken = '123|actual_token_part|extra_part';
        };

        $sensorMock = Mockery::mock($sensor);
        $sensorMock->shouldReceive('tokens->where->delete')->andReturn(true);
        $sensorMock->shouldReceive('createToken')->andReturn($mockToken);

        $token = $this->service->createOrRegenerateToken($sensorMock);

        // With limit=2, this should return 'actual_token_part|extra_part'
        // With limit=3, this would return 'actual_token_part'
        expect($token)->toBe('actual_token_part|extra_part');
    });
});

describe('getAssignedSensorsHealthStatus', function () {
    beforeEach(function () {
        // Ensure cache does not interfere between tests
        Cache::clear();
        Carbon::setTestNow('2025-08-09 18:00:00');
    });

    it('returns empty payload when no active assignments', function () {
        $org = Organization::factory()->create();
        Sensor::factory()->count(2)->withOrganization($org)->create();

        $result = $this->service->getAssignedSensorsHealthStatus($org);

        expect($result['total'])->toBe(0)
            ->and($result['healthy'])->toBe([])
            ->and($result['suspicious'])->toBe([])
            ->and($result['unhealthy'])->toBe([])
            ->and($result['last_updated'])->toBeString();
    });

    it('categorizes sensors into healthy, suspicious and unhealthy', function () {
        $org = Organization::factory()->create();

        $healthy = Sensor::factory()->withOrganization($org)->create(['serial' => 'H']);
        $suspicious = Sensor::factory()->withOrganization($org)->create(['serial' => 'S']);
        $unhealthy = Sensor::factory()->withOrganization($org)->create(['serial' => 'U']);

        // Active assignments now
        Assignment::factory()->withSensor($healthy)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);
        Assignment::factory()->withSensor($suspicious)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);
        Assignment::factory()->withSensor($unhealthy)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);

        // Healthy: recent and any non-zero
        IntervalCount::factory()->create([
            'sensor_id' => $healthy->id,
            'ts_from' => Carbon::now()->subMinutes(1)->subSeconds(30),
            'ts_to' => Carbon::now()->subMinute(),
            'count_in' => 1,
            'count_out' => 0,
        ]);

        // Suspicious: recent but all zeros in last window
        foreach (range(1, 3) as $i) {
            IntervalCount::factory()->create([
                'sensor_id' => $suspicious->id,
                'ts_from' => Carbon::now()->subMinutes(1)->subSeconds(55 - $i),
                'ts_to' => Carbon::now()->subMinutes(1)->subSeconds(50 - $i),
                'count_in' => 0,
                'count_out' => 0,
            ]);
        }

        // Unhealthy: latest not recent
        IntervalCount::factory()->create([
            'sensor_id' => $unhealthy->id,
            'ts_from' => Carbon::now()->subMinutes(5),
            'ts_to' => Carbon::now()->subMinutes(3),
            'count_in' => 10,
            'count_out' => 10,
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);

        expect($result['total'])->toBe(3)
            ->and(collect($result['healthy'])->pluck('serial')->toArray())->toContain('H')
            ->and(collect($result['suspicious'])->pluck('serial')->toArray())->toContain('S')
            ->and(collect($result['unhealthy'])->pluck('serial')->toArray())->toContain('U')
            ->and($result['all_healthy'])->toBeFalse()
            ->and($result['last_updated'])->toBeString();

        // ensure interval_counts contain both count_in and count_out
        $healthyItem = collect($result['healthy'])->first();
        expect($healthyItem['interval_counts'][0])->toHaveKeys(['ts_from', 'ts_to', 'count_in', 'count_out']);
    });

    it('only includes sensors from the given organization', function () {
        $org = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();

        $foreignSensor = Sensor::factory()->withOrganization($foreignOrg)->create(['serial' => 'X']);
        Assignment::factory()->withSensor($foreignSensor)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $foreignSensor->id,
            'ts_to' => Carbon::now(),
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);
        expect($result['total'])->toBe(0)
            ->and($result['all_healthy'])->toBeFalse()
            ->and($result['last_updated'])->toBeString();
    });

    it('caches the result for a short time', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'C-1']);
        Assignment::factory()->withSensor($sensor)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_to' => Carbon::now(),
            'count_in' => 1,
        ]);

        $first = $this->service->getAssignedSensorsHealthStatus($org);

        // Change data but within cache ttl, result should remain same
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_to' => Carbon::now()->addSecond(),
            'count_in' => 5,
        ]);

        $second = $this->service->getAssignedSensorsHealthStatus($org);

        expect($second)->toEqual($first);

        // Move time forward beyond TTL (5s) and expect change
        Carbon::setTestNow(Carbon::now()->addSeconds(6));
        $third = $this->service->getAssignedSensorsHealthStatus($org);
        expect($third)->not->toEqual($first);

        Carbon::setTestNow();
    });

    it('sets all_healthy to true when all sensors are healthy (including single sensor)', function () {
        $org = Organization::factory()->create();

        // Single healthy sensor
        $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'ONLY']);
        Assignment::factory()->withSensor($sensor)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => Carbon::now()->subMinutes(1)->subSeconds(30),
            'ts_to' => Carbon::now()->subMinute(),
            'count_in' => 3,
            'count_out' => 0,
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);

        expect($result['total'])->toBe(1)
            ->and($result['all_healthy'])->toBeTrue()
            ->and(count($result['healthy']))->toBe(1)
            ->and($result['suspicious'])->toBe([])
            ->and($result['unhealthy'])->toBe([]);
    });

    it('treats exactly-2-minutes-old data as recent', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'BND']);
        Assignment::factory()->withSensor($sensor)->create([
            'active_from' => Carbon::now()->subHour(),
            'active_to' => Carbon::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => Carbon::now()->subMinutes(3),
            'ts_to' => Carbon::now()->subMinutes(2), // exactly at threshold
            'count_in' => 1,
            'count_out' => 0,
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);
        expect($result['healthy'])->toHaveCount(1)
            ->and(collect($result['healthy'])->pluck('serial')->toArray())->toContain('BND');
    });
});
