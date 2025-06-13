<?php

use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Create test permissions
    $this->testPermission = Permission::create(['name' => 'test.permission']);
    $this->adminPermission = Permission::create(['name' => 'admin.permission']);
    
    // Create test role
    $this->testRole = Role::create(['name' => 'test-role']);
    $this->testRole->givePermissionTo($this->adminPermission);
    
    // Clear any existing cache
    Cache::flush();
});

it('triggers PermissionDetachedListener when permission is removed from user', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);
    
    // Give permission to user first
    $this->user->givePermissionTo($this->testPermission);
    
    // Cache the permission check result
    $result1 = GlobalPermissionService::canGlobally($this->user, 'test.permission');
    expect($result1)->toBeTrue();
    
    // Remove the permission - this should trigger PermissionDetachedListener
    $this->user->revokePermissionTo($this->testPermission);
    
    // Reset user relations to ensure fresh data is loaded
    $this->user->unsetRelation('roles')->unsetRelation('permissions');
    
    // Check that cache was cleared and permission is now false
    $result2 = GlobalPermissionService::canGlobally($this->user, 'test.permission');
    expect($result2)->toBeNull();
});

it('triggers RoleAttachedListener when role is assigned to user', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID  
    setPermissionsOrgId(GLOBAL_ORG_ID);
    
    // Check initial state (should be null)
    $result1 = GlobalPermissionService::canGlobally($this->user, 'admin.permission');
    expect($result1)->toBeNull();
    
    // Assign role to user - this should trigger RoleAttachedListener
    $this->user->assignRole($this->testRole);
    
    // Reset user relations to ensure fresh data is loaded
    $this->user->unsetRelation('roles')->unsetRelation('permissions');
    
    // Check that cache was cleared and permission is now available through role
    $result2 = GlobalPermissionService::canGlobally($this->user, 'admin.permission');
    expect($result2)->toBeTrue();
});

it('triggers RoleDetachedListener when role is removed from user', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);
    
    // Assign role to user first
    $this->user->assignRole($this->testRole);
    
    // Cache the permission check result
    $result1 = GlobalPermissionService::canGlobally($this->user, 'admin.permission');
    expect($result1)->toBeTrue();
    
    // Remove the role - this should trigger RoleDetachedListener
    $this->user->removeRole($this->testRole);
    
    // Reset user relations to ensure fresh data is loaded
    $this->user->unsetRelation('roles')->unsetRelation('permissions');
    
    // Check that cache was cleared and permission is now null
    $result2 = GlobalPermissionService::canGlobally($this->user, 'admin.permission');
    expect($result2)->toBeNull();
});

it('handles PermissionDetachedListener when event model is not a User', function () {
    // Set the permissions organization ID to GLOBAL_ORG_ID
    setPermissionsOrgId(GLOBAL_ORG_ID);
    
    // Create a permission and give it to the user
    $this->user->givePermissionTo($this->testPermission);
    
    // Cache some data
    GlobalPermissionService::canGlobally($this->user, 'test.permission');
    
    // Create an event with a non-User model (to test the null return path)
    $event = new \Spatie\Permission\Events\PermissionDetached(
        $this->testPermission, // Use permission as model instead of user
        $this->testPermission->id
    );
    
    $listener = app(\App\Listeners\Permissions\PermissionDetachedListener::class);
    
    // This should not clear cache since model is not a User
    $userId = $listener->getUserId($event);
    expect($userId)->toBeNull();
    
    // Handle the event
    $listener->handle($event);
    
    // Cache should still be intact since no user ID was found
    // (This test ensures the null path in getUserId is covered)
});

it('handles RoleAttachedListener when event model is not a User', function () {
    // Create an event with a non-User model
    $event = new \Spatie\Permission\Events\RoleAttached(
        $this->testRole, // Use role as model instead of user
        $this->testRole->id
    );
    
    $listener = app(\App\Listeners\Permissions\RoleAttachedListener::class);
    
    // This should return null since model is not a User
    $userId = $listener->getUserId($event);
    expect($userId)->toBeNull();
    
    // Handle the event
    $listener->handle($event);
    
    // (This test ensures the null path in getUserId is covered)
});

it('handles RoleDetachedListener when event model is not a User', function () {
    // Create an event with a non-User model
    $event = new \Spatie\Permission\Events\RoleDetached(
        $this->testRole, // Use role as model instead of user
        $this->testRole->id
    );
    
    $listener = app(\App\Listeners\Permissions\RoleDetachedListener::class);
    
    // This should return null since model is not a User
    $userId = $listener->getUserId($event);
    expect($userId)->toBeNull();
    
    // Handle the event
    $listener->handle($event);
    
    // (This test ensures the null path in getUserId is covered)
});

it('handles RoleDetachedListener when getUserId returns null', function () {
    // Test the case where getUserId returns null (covering the 47 line return null)
    $listener = app(\App\Listeners\Permissions\RoleDetachedListener::class);
    
    // Create a real RoleDetached event with a Role model (not User)
    $event = new \Spatie\Permission\Events\RoleDetached(
        $this->testRole, // Use role as model instead of user  
        $this->testRole->id
    );
    
    // This should return null since model is not a User and no modelId property exists
    $userId = $listener->getUserId($event);
    expect($userId)->toBeNull();
    
    // Handle the event - should not cause any issues even when userId is null
    $listener->handle($event);
    
    // The important part is that we've exercised the null return path
});
