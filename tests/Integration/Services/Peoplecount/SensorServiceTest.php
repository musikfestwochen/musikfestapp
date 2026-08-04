<?php

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use App\Services\Peoplecount\SensorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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

    it('returns all active sensors for global organization', function () {
        $org = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();
        Sensor::factory()->withOrganization($org)->create();
        Sensor::factory()->withOrganization($foreignOrg)->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        $result = $this->service->getSensors();

        expect($result)->toHaveCount(2);
    });

    it('returns archived sensors only', function () {
        $org = Organization::factory()->create();
        Sensor::factory()->withOrganization($org)->create();
        $archivedSensor = Sensor::factory()->withOrganization($org)->create(['archived_at' => now()]);

        setPermissionsOrgId($org->id);

        $result = $this->service->getSensors(true);

        expect($result)->toHaveCount(1)
            ->and($result->first()->is($archivedSensor))->toBeTrue();
    });

    it('reports whether each listed sensor has an active token', function () {
        $org = Organization::factory()->create();
        $sensorWithToken = Sensor::factory()->withOrganization($org)->create(['serial' => 'A']);
        $sensorWithoutToken = Sensor::factory()->withOrganization($org)->create(['serial' => 'B']);
        $sensorWithToken->createToken(SensorService::SENSOR_TOKEN_NAME);

        setPermissionsOrgId($org->id);

        $sensors = $this->service->getSensors();

        expect($sensors->firstWhere('id', $sensorWithToken->id)?->has_active_token)->toBeTruthy()
            ->and($sensors->firstWhere('id', $sensorWithoutToken->id)?->has_active_token)->toBeFalsy();
    });

    it('does not report a legacy-named token as active', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        $sensor->createToken('legacy-token');

        setPermissionsOrgId($org->id);

        $sensors = $this->service->getSensors();

        expect($sensors->firstWhere('id', $sensor->id)?->has_active_token)->toBeFalsy();
    });
});

describe('archive', function () {
    it('archives and restores a sensor', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId($org->id);

        $archived = $this->service->archive($sensor);
        expect($archived->archived_at)->not->toBeNull();

        $restored = $this->service->unarchive($sensor);
        expect($restored->archived_at)->toBeNull();
    });

    it('allows global organization to manage any sensor', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();

        setPermissionsOrgId(GLOBAL_ORG_ID);

        expect($this->service->archive($sensor)->archived_at)->not->toBeNull();
    });

    it('blocks managing another organization sensor', function () {
        $org = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($foreignOrg)->create();

        setPermissionsOrgId($org->id);
        expect(fn () => $this->service->archive($sensor))->toThrow(AuthorizationException::class);
    });

    it('revokes every sensor token when archiving', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
        $sensor->createToken('legacy-token');

        setPermissionsOrgId($org->id);

        $this->service->archive($sensor);

        expect($sensor->tokens()->count())->toBe(0);
    });

    it('restores a sensor without issuing a token', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['archived_at' => now()]);

        setPermissionsOrgId($org->id);

        $restored = $this->service->unarchive($sensor);

        expect($restored->archived_at)->toBeNull()
            ->and($sensor->tokens()->count())->toBe(0);
    });
});

describe('delete', function () {
    it('allows deleting a sensor with unused shares', function () {
        $owner = Organization::factory()->create();
        $borrower = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($owner)->create();
        SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();

        setPermissionsOrgId($owner->id);

        $this->service->delete($sensor);

        $this->assertSoftDeleted('peoplecount_sensors', ['id' => $sensor->id]);
    });

    it('blocks deleting a sensor used by a shared assignment', function () {
        $owner = Organization::factory()->create();
        $borrower = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($owner)->create();
        $share = SensorShare::factory()->withSensor($sensor)->withBorrowerOrganization($borrower)->create();
        $event = Event::factory()->withOrganization($borrower)->create();
        $area = Area::factory()->withEvent($event)->create();
        Assignment::factory()->withEvent($event)->withArea($area)->withSensor($sensor)->create([
            'sensor_share_id' => $share->id,
        ]);

        setPermissionsOrgId($owner->id);
        expect(fn () => $this->service->delete($sensor))->toThrow(ValidationException::class);
    });

    it('revokes every token when deleting a sensor', function () {
        $owner = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($owner)->create();
        $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
        $sensor->createToken('legacy-token');

        setPermissionsOrgId($owner->id);

        $this->service->delete($sensor);

        $this->assertSoftDeleted('peoplecount_sensors', ['id' => $sensor->id]);
        expect($sensor->tokens()->count())->toBe(0);
    });
});

describe('createWithToken', function () {
    it('creates a sensor and returns the token secret', function () {
        $org = Organization::factory()->create();
        $attributes = [
            'organization_id' => $org->id,
            'vendor' => 'TestVendor',
            'model' => 'TestModel',
            'serial' => 'SN123456',
        ];

        $result = $this->service->createWithToken($attributes);
        $accessToken = PersonalAccessToken::findToken($result['token']);

        expect($result['sensor'])->toBeInstanceOf(Sensor::class)
            ->and($result['sensor']->organization_id)->toBe($org->id)
            ->and($result['token'])->not->toContain('|')
            ->and($accessToken)->not->toBeNull()
            ->and($accessToken->tokenable->is($result['sensor']))->toBeTrue()
            ->and($accessToken->name)->toBe(SensorService::SENSOR_TOKEN_NAME)
            ->and($accessToken->token)->toBe(hash('sha256', $result['token']));
    });
});

describe('createOrRegenerateToken', function () {
    it('creates a new token for a sensor', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        setPermissionsOrgId($org->id);

        $token = $this->service->createOrRegenerateToken($sensor);

        expect($token)->not->toBeEmpty()
            ->and($token)->not->toContain('|')
            ->and(PersonalAccessToken::findToken($token))->not->toBeNull();

        $dbToken = $sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->first();
        expect($dbToken)->not->toBeNull();
    });

    it('regenerates token and deletes previous one', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        setPermissionsOrgId($org->id);

        $firstToken = $this->service->createOrRegenerateToken($sensor);
        $secondToken = $this->service->createOrRegenerateToken($sensor);

        expect($sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->count())->toBe(1)
            ->and($secondToken)->not->toBe($firstToken)
            ->and(PersonalAccessToken::findToken($firstToken))->toBeNull()
            ->and(PersonalAccessToken::findToken($secondToken))->not->toBeNull();
    });

    it('regenerates without deleting differently named tokens', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        $legacyToken = $sensor->createToken('legacy-token')->plainTextToken;
        setPermissionsOrgId($org->id);

        $newToken = $this->service->createOrRegenerateToken($sensor);

        expect(PersonalAccessToken::findToken($legacyToken))->not->toBeNull()
            ->and($sensor->tokens()->where('name', SensorService::SENSOR_TOKEN_NAME)->count())->toBe(1)
            ->and(PersonalAccessToken::findToken($newToken))->not->toBeNull();
    });

    it('rejects token rotation for archived sensors', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['archived_at' => now()]);
        setPermissionsOrgId($org->id);

        expect(fn () => $this->service->createOrRegenerateToken($sensor))
            ->toThrow(ValidationException::class, 'Archived sensors cannot receive API tokens.');
    });

    it('rejects managing another organization sensor', function () {
        $org = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($foreignOrg)->create();

        setPermissionsOrgId($org->id);
        expect(fn () => $this->service->createOrRegenerateToken($sensor))->toThrow(AuthorizationException::class);
    });
});

describe('revokeTokens', function () {
    it('revokes every sensor token', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create();
        $sensor->createToken(SensorService::SENSOR_TOKEN_NAME);
        $sensor->createToken('legacy-token');
        setPermissionsOrgId($org->id);

        $this->service->revokeTokens($sensor);

        expect($sensor->tokens()->count())->toBe(0);
    });

    it('blocks revoking another organization sensor', function () {
        $org = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($foreignOrg)->create();

        setPermissionsOrgId($org->id);
        expect(fn () => $this->service->revokeTokens($sensor))->toThrow(AuthorizationException::class);
    });
});

describe('getAssignedSensorsHealthStatus', function () {
    beforeEach(function () {
        // Ensure cache does not interfere between tests
        Cache::clear();
        Date::setTestNow('2025-08-09 18:00:00');
    });

    it('returns empty payload when no active assignments', function () {
        $org = Organization::factory()->create();
        Sensor::factory()->count(2)->withOrganization($org)->create();

        $result = $this->service->getAssignedSensorsHealthStatus($org);

        expect($result['total'])->toBe(0)
            ->and($result['healthy'])->toBeEmpty()
            ->and($result['suspicious'])->toBeEmpty()
            ->and($result['unhealthy'])->toBeEmpty()
            ->and($result['last_updated'])->toBeString();
    });

    it('categorizes sensors into healthy, suspicious and unhealthy', function () {
        $org = Organization::factory()->create();

        $healthy = Sensor::factory()->withOrganization($org)->create(['serial' => 'H']);
        $suspicious = Sensor::factory()->withOrganization($org)->create(['serial' => 'S']);
        $unhealthy = Sensor::factory()->withOrganization($org)->create(['serial' => 'U']);

        // Active assignments now
        Assignment::factory()->withSensor($healthy)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);
        Assignment::factory()->withSensor($suspicious)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);
        Assignment::factory()->withSensor($unhealthy)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);

        // Healthy: recent and any non-zero
        IntervalCount::factory()->create([
            'sensor_id' => $healthy->id,
            'ts_from' => Date::now()->subMinutes(1)->subSeconds(30),
            'ts_to' => Date::now()->subMinute(),
            'count_in' => 1,
            'count_out' => 0,
        ]);

        // Suspicious: recent but all zeros in last window
        foreach (range(1, 3) as $i) {
            IntervalCount::factory()->create([
                'sensor_id' => $suspicious->id,
                'ts_from' => Date::now()->subMinutes(1)->subSeconds(55 - $i),
                'ts_to' => Date::now()->subMinutes(1)->subSeconds(50 - $i),
                'count_in' => 0,
                'count_out' => 0,
            ]);
        }

        // Unhealthy: latest not recent
        IntervalCount::factory()->create([
            'sensor_id' => $unhealthy->id,
            'ts_from' => Date::now()->subMinutes(5),
            'ts_to' => Date::now()->subMinutes(3),
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
        $foreignEvent = Event::factory()->withOrganization($foreignOrg)->create();
        $foreignArea = Area::factory()->withEvent($foreignEvent)->create();

        Assignment::factory()->withEvent($foreignEvent)->withArea($foreignArea)->withSensor($foreignSensor)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $foreignSensor->id,
            'ts_to' => Date::now(),
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);
        expect($result['total'])->toBe(0)
            ->and($result['all_healthy'])->toBeTrue()
            ->and($result['last_updated'])->toBeString();
    });

    it('uses the assignment label only when one assignment is active', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['name' => 'Physical Sensor']);
        Assignment::factory()->withSensor($sensor)->create([
            'label' => 'Main Entrance',
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);

        $singleAssignmentResult = $this->service->getAssignedSensorsHealthStatus($org);

        expect($singleAssignmentResult['unhealthy'][0]['label'])->toBe('Main Entrance');

        Cache::clear();
        Assignment::factory()->withSensor($sensor)->create([
            'label' => 'Main Entrance Flipped',
            'direction_flipped' => true,
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);

        $multipleAssignmentsResult = $this->service->getAssignedSensorsHealthStatus($org);

        expect($multipleAssignmentsResult['total'])->toBe(1)
            ->and($multipleAssignmentsResult['unhealthy'][0]['label'])->toBeNull()
            ->and($multipleAssignmentsResult['unhealthy'][0]['name'])->toBe('Physical Sensor');
    });

    it('caches the result for a short time', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'C-1']);
        Assignment::factory()->withSensor($sensor)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_to' => Date::now(),
            'count_in' => 1,
        ]);

        $first = $this->service->getAssignedSensorsHealthStatus($org);

        // Change data but within cache ttl, result should remain same
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_to' => Date::now()->addSecond(),
            'count_in' => 5,
        ]);

        $second = $this->service->getAssignedSensorsHealthStatus($org);

        expect($second)->toEqual($first);

        // Move time forward beyond TTL (5s) and expect change
        Date::setTestNow(Date::now()->addSeconds(6));
        $third = $this->service->getAssignedSensorsHealthStatus($org);
        expect($third)->not->toEqual($first);

        Date::setTestNow();
    });

    it('sets all_healthy to true when all sensors are healthy (including single sensor)', function () {
        $org = Organization::factory()->create();

        // Single healthy sensor
        $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'ONLY']);
        Assignment::factory()->withSensor($sensor)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => Date::now()->subMinutes(1)->subSeconds(30),
            'ts_to' => Date::now()->subMinute(),
            'count_in' => 3,
            'count_out' => 0,
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);

        expect($result['total'])->toBe(1)
            ->and($result['all_healthy'])->toBeTrue()
            ->and($result['healthy'])->toHaveCount(1)
            ->and($result['suspicious'])->toBeEmpty()
            ->and($result['unhealthy'])->toBeEmpty();
    });

    it('treats exactly-2-minutes-old data as recent', function () {
        $org = Organization::factory()->create();
        $sensor = Sensor::factory()->withOrganization($org)->create(['serial' => 'BND']);
        Assignment::factory()->withSensor($sensor)->create([
            'active_from' => Date::now()->subHour(),
            'active_to' => Date::now()->addHour(),
        ]);
        IntervalCount::factory()->create([
            'sensor_id' => $sensor->id,
            'ts_from' => Date::now()->subMinutes(3),
            'ts_to' => Date::now()->subMinutes(2), // exactly at threshold
            'count_in' => 1,
            'count_out' => 0,
        ]);

        $result = $this->service->getAssignedSensorsHealthStatus($org);
        expect($result['healthy'])->toHaveCount(1)
            ->and(collect($result['healthy'])->pluck('serial')->toArray())->toContain('BND');
    });
});
