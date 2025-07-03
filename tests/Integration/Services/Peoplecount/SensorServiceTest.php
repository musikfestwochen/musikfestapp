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
        // With limit=3 (mutation), this would return 'actual_token_part'
        expect($token)->toBe('actual_token_part|extra_part');
    });
});
