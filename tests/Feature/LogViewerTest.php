<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

it('protects log viewer in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->actingAs(User::factory()->create())
        ->get('/admin/logs')
        ->assertForbidden();
});

it('allows log admins to view logs', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $user = User::factory()->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);
    Permission::findOrCreate('admin.logs');
    $user->givePermissionTo('admin.logs');

    $this->actingAs($user)
        ->get('/admin/logs')
        ->assertSuccessful();
});

it('allows log admins to load log files', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $user = User::factory()->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);
    Permission::findOrCreate('admin.logs');
    $user->givePermissionTo('admin.logs');

    $this->actingAs($user)
        ->get('/admin/logs/api/files')
        ->assertSuccessful();
});
