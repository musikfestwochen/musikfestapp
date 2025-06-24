<?php

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

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
});
