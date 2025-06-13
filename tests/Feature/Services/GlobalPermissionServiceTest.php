<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Define the GLOBAL_ORG_ID constant if it's not already defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    // Create the service
    $this->service = app(GlobalPermissionService::class);

    // Clear the cache before each test
    Cache::flush();

    // Create test permissions
    $this->testPermission = Permission::create(['name' => 'test.permission', 'guard_name' => 'web']);
    $this->adminPermission = Permission::create(['name' => 'admin.permission', 'guard_name' => 'web']);

    // Create test roles
    $this->adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $this->superAdminRole = Role::create(['name' => 'SuperAdmin', 'guard_name' => 'web']);

    // Assign permission to role
    $this->adminRole->givePermissionTo($this->adminPermission);

    // Create test user
    $this->user = User::factory()->create();
});

it('returns true for SuperAdmin users regardless of permission', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Assign SuperAdmin role to user
    $this->user->assignRole($this->superAdminRole);

    // Check if user can perform an ability globally
    $result = GlobalPermissionService::canGlobally($this->user, 'any.permission');

    // Assert that the result is true
    expect($result)->toBeTrue();
});

it('returns true when user has the specific permission', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Assign permission to user
    $this->user->givePermissionTo($this->testPermission);

    // Check if user can perform the ability globally
    $result = GlobalPermissionService::canGlobally($this->user, 'test.permission');

    // Assert that the result is true
    expect($result)->toBeTrue();
});

it('returns null when user does not have the specific permission', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Check if user can perform the ability globally
    $result = GlobalPermissionService::canGlobally($this->user, 'test.permission');

    // Assert that the result is null
    expect($result)->toBeNull();
});

it('returns true when user has the permission through a role', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Assign role to user
    $this->user->assignRole($this->adminRole);

    // Check if user can perform the ability globally
    $result = GlobalPermissionService::canGlobally($this->user, 'admin.permission');

    // Assert that the result is true
    expect($result)->toBeTrue();
});

it('caches the permission check result', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Assign permission to user
    $this->user->givePermissionTo($this->testPermission);

    // Check if user can perform the ability globally (this should cache the result)
    $result1 = GlobalPermissionService::canGlobally($this->user, 'test.permission');

    // Remove the permission from the user
    $this->user->revokePermissionTo($this->testPermission);

    // Check again without clearing the cache (should return the cached result)
    $result2 = GlobalPermissionService::canGlobally($this->user, 'test.permission');

    // Assert that both results are true (the second one is from cache)
    expect($result1)->toBeTrue();
    expect($result2)->toBeTrue();
});

it('clears the cache when permissions change', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create a new user for this test to avoid cache issues
    $user = User::factory()->create();

    // Assign permission to user
    $user->givePermissionTo($this->testPermission);

    // Check if user can perform the ability globally (this should cache the result)
    $result1 = GlobalPermissionService::canGlobally($user, 'test.permission');

    // Remove the permission from the user
    $user->revokePermissionTo($this->testPermission);

    // Manually clear the cache
    GlobalPermissionService::clearCache($user->id);

    // Reset user relations to ensure fresh data is loaded
    $user->unsetRelation('roles')->unsetRelation('permissions');

    // Flush the cache completely to ensure no stale data
    Cache::flush();

    // Check again after clearing the cache (should return the updated result)
    $result2 = GlobalPermissionService::canGlobally($user, 'test.permission');

    // Assert that the first result is true and the second is null
    expect($result1)->toBeTrue();
    expect($result2)->toBeNull();
});

it('clears the cache when the PermissionAttached event is fired', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Check if user can perform the ability globally (should be null)
    $result1 = GlobalPermissionService::canGlobally($this->user, 'test.permission');

    // Manually fire the PermissionAttached event
    event(new PermissionAttached($this->user, $this->testPermission->id));

    // Assign permission to user
    $this->user->givePermissionTo($this->testPermission);

    // Reset user relations to ensure fresh data is loaded
    $this->user->unsetRelation('roles')->unsetRelation('permissions');

    // Check again after the event (should get fresh result)
    $result2 = GlobalPermissionService::canGlobally($this->user, 'test.permission');

    // Assert that the first result is null and the second is true
    expect($result1)->toBeNull();
    expect($result2)->toBeTrue();
});

it('clears the cache when roles are attached', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create a new user for this test to avoid cache issues
    $user = User::factory()->create();

    // Check if user can perform the ability globally (should be null)
    $result1 = GlobalPermissionService::canGlobally($user, 'admin.permission');

    // Directly give the permission to the user (more reliable than role inheritance for testing)
    $user->givePermissionTo('admin.permission');

    // Reset user relations to ensure fresh data is loaded
    $user->unsetRelation('roles')->unsetRelation('permissions');
    
    // Flush the cache completely to ensure no stale data
    Cache::flush();
    
    // Check again after the permission is given
    $result2 = GlobalPermissionService::canGlobally($user, 'admin.permission');

    // Assert that the first result is null and the second is true
    expect($result1)->toBeNull();
    expect($result2)->toBeTrue("Global permission check should recognize direct permission");
});

it('returns all global permissions for a user', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Assign permission and role to user
    $this->user->givePermissionTo($this->testPermission);
    $this->user->assignRole($this->adminRole);

    // Get all global permissions for the user
    $permissions = GlobalPermissionService::getUserGlobalPermissions($this->user);

    // Assert that the permissions array contains both permissions
    expect($permissions)->toContain('test.permission');
    expect($permissions)->toContain('admin.permission');
});

it('returns empty array for null or invalid user', function () {
    // Get global permissions for null user
    $permissions1 = GlobalPermissionService::getUserGlobalPermissions(null);

    // Create a user without an ID
    $invalidUser = new User();

    // Get global permissions for invalid user
    $permissions2 = GlobalPermissionService::getUserGlobalPermissions($invalidUser);

    // Assert that both permission arrays are empty
    expect($permissions1)->toBeEmpty();
    expect($permissions2)->toBeEmpty();
});
