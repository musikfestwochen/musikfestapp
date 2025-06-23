<?php

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

covers(App\Services\Peoplecount\SensorService::class);

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
