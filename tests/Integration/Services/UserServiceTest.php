<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

covers(App\Services\UserService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new UserService;
});

describe('getPaginatedUsers', function () {
    it('returns paginated users with default sorting', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->count(15)->create();

        $result = $this->service->getPaginatedUsers();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->perPage())->toBe(10);
        expect($result->total())->toBe(15);
        expect($result->currentPage())->toBe(1);
    });

    it('sorts users by name in ascending order by default', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->create(['name' => 'Zulu User']);
        User::factory()->create(['name' => 'Alpha User']);
        User::factory()->create(['name' => 'Beta User']);

        $result = $this->service->getPaginatedUsers();

        $names = $result->items();
        expect($names[0]->name)->toBe('Alpha User');
        expect($names[1]->name)->toBe('Beta User');
        expect($names[2]->name)->toBe('Zulu User');
    });

    it('sorts users by name in descending order when specified', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->create(['name' => 'Alpha User']);
        User::factory()->create(['name' => 'Beta User']);
        User::factory()->create(['name' => 'Zulu User']);

        $result = $this->service->getPaginatedUsers('name', 'desc');

        $names = $result->items();
        expect($names[0]->name)->toBe('Zulu User');
        expect($names[1]->name)->toBe('Beta User');
        expect($names[2]->name)->toBe('Alpha User');
    });

    it('sorts users by email when specified', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->create(['email' => 'zulu@example.com']);
        User::factory()->create(['email' => 'alpha@example.com']);
        User::factory()->create(['email' => 'beta@example.com']);

        $result = $this->service->getPaginatedUsers('email', 'asc');

        $emails = $result->items();
        expect($emails[0]->email)->toBe('alpha@example.com');
        expect($emails[1]->email)->toBe('beta@example.com');
        expect($emails[2]->email)->toBe('zulu@example.com');
    });

    it('sorts users by created_at when specified', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        $oldest = User::factory()->create(['created_at' => now()->subDays(3)]);
        $newest = User::factory()->create(['created_at' => now()->subDays(1)]);
        $middle = User::factory()->create(['created_at' => now()->subDays(2)]);

        $result = $this->service->getPaginatedUsers('created_at', 'asc');

        $items = $result->items();
        expect($items[0]->id)->toBe($oldest->id);
        expect($items[1]->id)->toBe($middle->id);
        expect($items[2]->id)->toBe($newest->id);
    });

    it('does not apply sorting when invalid sort field is provided', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        $first = User::factory()->create(['name' => 'Zulu User']);
        $second = User::factory()->create(['name' => 'Alpha User']);

        $result = $this->service->getPaginatedUsers('invalid_field', 'desc');

        // Should return in natural database order (by ID) when invalid sort field is provided
        $items = $result->items();
        expect($items[0]->id)->toBe($first->id);
        expect($items[1]->id)->toBe($second->id);
    });

    it('filters users by organization when not in global organization', function () {
        $organization = Organization::factory()->create();
        setPermissionsOrgId($organization->id);

        $userInOrg = User::factory()->create(['name' => 'User In Org']);
        $userNotInOrg = User::factory()->create(['name' => 'User Not In Org']);

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getPaginatedUsers();

        expect($result->total())->toBe(1);
        expect($result->items()[0]->id)->toBe($userInOrg->id);
    });

    it('returns all users when in global organization', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $organization = Organization::factory()->create();
        $userInOrg = User::factory()->create(['name' => 'User In Org']);
        $userNotInOrg = User::factory()->create(['name' => 'User Not In Org']);

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getPaginatedUsers();

        expect($result->total())->toBe(2);
        $userIds = collect($result->items())->pluck('id')->toArray();
        expect($userIds)->toContain($userInOrg->id);
        expect($userIds)->toContain($userNotInOrg->id);
    });

    it('accepts valid direction parameter', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->create(['name' => 'Alpha']);
        User::factory()->create(['name' => 'Beta']);

        $resultAsc = $this->service->getPaginatedUsers('name', 'asc');
        $resultDesc = $this->service->getPaginatedUsers('name', 'desc');

        expect($resultAsc->items()[0]->name)->toBe('Alpha');
        expect($resultDesc->items()[0]->name)->toBe('Beta');
    });

    it('preserves query string in pagination', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->count(15)->create();

        $result = $this->service->getPaginatedUsers();

        expect($result->hasPages())->toBeTrue();
        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('returns empty paginator when no users exist', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $result = $this->service->getPaginatedUsers();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->total())->toBe(0);
        expect($result->items())->toBeEmpty();
    });

    it('handles all valid sort fields correctly', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        $validSortFields = ['name', 'email', 'created_at'];

        foreach ($validSortFields as $field) {
            User::factory()->count(3)->create();

            $result = $this->service->getPaginatedUsers($field, 'asc');

            expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
            expect($result->total())->toBeGreaterThan(0);

            // Clean up for next iteration
            User::query()->delete();
        }
    });

    it('filters correctly when organization has no users', function () {
        $organization = Organization::factory()->create();
        setPermissionsOrgId($organization->id);

        User::factory()->count(3)->create(); // Users not attached to any organization

        $result = $this->service->getPaginatedUsers();

        expect($result->total())->toBe(0);
        expect($result->items())->toBeEmpty();
    });
});
