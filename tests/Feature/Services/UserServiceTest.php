<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Define the GLOBAL_ORG_ID constant if it's not already defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    // Create the service
    $this->service = new UserService;
});

it('returns all users when current organization is GLOBAL_ORG_ID', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create users
    $users = User::factory()->count(5)->create();

    // Get paginated users
    $result = $this->service->getPaginatedUsers();

    // Assert that all users are returned
    expect($result->total())->toBe($users->count());
});

it('returns only users belonging to the current organization', function () {
    // Create organizations
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    // Set the permissions organization ID to the organization
    setPermissionsOrgId($organization->id);

    // Create users and assign them to organizations
    $organizationUsers = User::factory()->count(3)->create();
    $otherUsers = User::factory()->count(2)->create();

    // Assign users to organizations
    foreach ($organizationUsers as $user) {
        $user->organizations()->attach($organization->id);
    }

    foreach ($otherUsers as $user) {
        $user->organizations()->attach($otherOrganization->id);
    }

    // Get paginated users
    $result = $this->service->getPaginatedUsers();

    // Assert that only users belonging to the organization are returned
    expect($result->total())->toBe($organizationUsers->count());
    foreach ($organizationUsers as $user) {
        expect($result->pluck('id'))->toContain($user->id);
    }

    foreach ($otherUsers as $user) {
        expect($result->pluck('id'))->not->toContain($user->id);
    }
});

it('sorts users by name in ascending order by default', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create users with specific names
    $userC = User::factory()->create(['name' => 'Charlie']);
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    // Get paginated users
    $result = $this->service->getPaginatedUsers();

    // Assert that users are sorted by name in ascending order
    expect($result->pluck('name')->toArray())->toBe(['Alice', 'Bob', 'Charlie']);
});

it('sorts users by specified column and direction', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create users with specific emails
    $userC = User::factory()->create(['email' => 'charlie@example.com']);
    $userA = User::factory()->create(['email' => 'alice@example.com']);
    $userB = User::factory()->create(['email' => 'bob@example.com']);

    // Get paginated users sorted by email in descending order
    $result = $this->service->getPaginatedUsers('email', 'desc');

    // Assert that users are sorted by email in descending order
    expect($result->pluck('email')->toArray())->toBe(['charlie@example.com', 'bob@example.com', 'alice@example.com']);
});

it('ignores invalid sort columns', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create users with specific names
    $userC = User::factory()->create(['name' => 'Charlie']);
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    // Get paginated users with an invalid sort column
    $result = $this->service->getPaginatedUsers('invalid_column', 'asc');

    // Assert that all users are returned (without checking the order)
    expect($result->count())->toBe(3);
    expect($result->pluck('name'))->toContain('Alice');
    expect($result->pluck('name'))->toContain('Bob');
    expect($result->pluck('name'))->toContain('Charlie');
});
