<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

covers(App\Services\UserService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new UserService;
});

describe('getUsers', function () {
    it('returns users', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->count(15)->create();

        $result = $this->service->getUsers();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(15);
    });

    it('filters users by organization when not in global organization', function () {
        $organization = Organization::factory()->create();
        setPermissionsOrgId($organization->id);

        $userInOrg = User::factory()->create(['name' => 'User In Org']);
        $userNotInOrg = User::factory()->create(['name' => 'User Not In Org']);

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getUsers();

        expect($result->count())->toBe(1);
    });

    it('returns all users when in global organization', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $organization = Organization::factory()->create();
        $userInOrg = User::factory()->create(['name' => 'User In Org']);
        $userNotInOrg = User::factory()->create(['name' => 'User Not In Org']);

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getUsers();

        expect($result->count())->toBe(2);
    });

    it('returns empty collection when no users exist', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $result = $this->service->getUsers();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('filters correctly when organization has no users', function () {
        $organization = Organization::factory()->create();
        setPermissionsOrgId($organization->id);

        User::factory()->count(3)->create(); // Users not attached to any organization

        $result = $this->service->getUsers();

        expect($result->count())->toBe(0);
    });

    it('uses strict comparison for GLOBAL_ORG_ID check', function ($orgId, $expectedCount) {
        // Test that string "0" is not treated as integer 0
        setPermissionsOrgId($orgId); // String zero instead of integer zero

        $organization = Organization::factory()->create();
        $userInOrg = User::factory()->create();
        $userNotInOrg = User::factory()->create();

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getUsers();

        // With loose comparison (!=), "0" == 0, so it returns all users (no filtering)
        // With strict comparison (!==), "0" !== 0, so it would filter by organization
        expect($result->count())->toBe($expectedCount);
    })->with(
        [
            [GLOBAL_ORG_ID, 2],
            [Str(GLOBAL_ORG_ID), 0], // String "0" should not match integer 0
        ]
    );

    it('properly filters users when multiple organizations exist', function () {
        $targetOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        setPermissionsOrgId($targetOrg->id);

        $userInTargetOrg = User::factory()->create(['name' => 'User In Target Org']);
        $userInOtherOrg = User::factory()->create(['name' => 'User In Other Org']);
        $userInBothOrgs = User::factory()->create(['name' => 'User In Both Orgs']);
        $userInNoOrg = User::factory()->create(['name' => 'User In No Org']);

        $userInTargetOrg->organizations()->attach($targetOrg);
        $userInOtherOrg->organizations()->attach($otherOrg);
        $userInBothOrgs->organizations()->attach([$targetOrg->id, $otherOrg->id]);

        $result = $this->service->getUsers();

        // Should only return users that belong to the target organization
        expect($result->count())->toBe(2)
            ->and($result->pluck('name')->toArray())->toContain('User In Target Org')
            ->and($result->pluck('name')->toArray())->toContain('User In Both Orgs')
            ->and($result->pluck('name')->toArray())->not->toContain('User In Other Org')
            ->and($result->pluck('name')->toArray())->not->toContain('User In No Org');
    });

    it('enforces organization ID matching in whereHas clause', function () {
        $correctOrg = Organization::factory()->create();
        $wrongOrg = Organization::factory()->create();
        setPermissionsOrgId($correctOrg->id);

        $userInCorrectOrg = User::factory()->create();
        $userInWrongOrg = User::factory()->create();

        $userInCorrectOrg->organizations()->attach($correctOrg);
        $userInWrongOrg->organizations()->attach($wrongOrg);

        $result = $this->service->getUsers();

        // This test specifically verifies that the where clause filters by organization ID
        // If the where clause is removed, this test should fail
        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(1)
            ->and($result->pluck('id')->toArray())->toContain($userInCorrectOrg->id)
            ->and($result->pluck('id')->toArray())->not->toContain($userInWrongOrg->id);
    });
});
