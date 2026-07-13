<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds and updates role metadata', function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

    $this->assertDatabaseHas('roles', [
        'name' => 'PeopleCountViewer',
        'display_name' => 'People count viewer',
        'description' => 'Can view people-count dashboards and data for this organization.',
    ]);

    Role::query()
        ->where('name', 'PeopleCountViewer')
        ->update([
            'display_name' => 'Old title',
            'description' => 'Old description',
        ]);

    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

    $this->assertDatabaseHas('roles', [
        'name' => 'PeopleCountViewer',
        'display_name' => 'People count viewer',
        'description' => 'Can view people-count dashboards and data for this organization.',
    ]);
});
