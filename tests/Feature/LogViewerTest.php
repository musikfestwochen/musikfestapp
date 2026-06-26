<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

it('protects log viewer in production', function () {
    config(['app.env' => 'production']);

    $this->actingAs(User::factory()->create())
        ->get('/admin/logs')
        ->assertForbidden();
});

it('allows pulse admins to view logs', function () {
    config(['app.env' => 'production']);

    $user = User::factory()->create();

    setPermissionsOrgId(GLOBAL_ORG_ID);
    Permission::findOrCreate('admin.logs');
    $user->givePermissionTo('admin.logs');

    $this->actingAs($user)
        ->get('/admin/logs')
        ->assertSuccessful();
});
