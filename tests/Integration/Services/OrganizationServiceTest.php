<?php

use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

covers(OrganizationService::class);

beforeEach(function () {
    $this->service = new OrganizationService;
});

describe('getOrganizations', function () {
    it('returns all organizations', function () {
        Organization::factory()->count(15)->create();

        $result = $this->service->getOrganizations();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(15);
    });

    it('returns empty collection when no organizations exist', function () {
        $result = $this->service->getOrganizations();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });
});
