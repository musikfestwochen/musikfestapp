<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'SuperAdmin']);

        // create permissions
        foreach (['create', 'destroy', 'edit', 'index', 'show', 'store', 'update', '*'] as $action) {

            // Admin Module
            Permission::create(['name' => 'admin.users.'.$action]);
            Permission::create(['name' => 'admin.organizations.'.$action]);

            // Organization Management Module
            Permission::create(['name' => 'orgmgmt.users.'.$action]);

            // People counting module
            Permission::create(['name' => 'peoplecount.sensors.'.$action]);
            Permission::create(['name' => 'peoplecount.events.'.$action]);
            Permission::create(['name' => 'peoplecount.areas.'.$action]);
            Permission::create(['name' => 'peoplecount.assignments.'.$action]);
        }

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create roles and assign created permissions

        Role::create(['name' => 'Admin'])
            ->givePermissionTo('admin.users.*')
            ->givePermissionTo('admin.organizations.*')
            ->givePermissionTo('orgmgmt.users.*')
            ->givePermissionTo('peoplecount.sensors.*')
            ->givePermissionTo('peoplecount.events.*')
            ->givePermissionTo('peoplecount.areas.*')
            ->givePermissionTo('peoplecount.assignments.*');

        Role::create(['name' => 'OrganizationAdmin'])
            ->givePermissionTo('orgmgmt.users.*')
            ->givePermissionTo('peoplecount.sensors.*')
            ->givePermissionTo('peoplecount.events.*')
            ->givePermissionTo('peoplecount.areas.*')
            ->givePermissionTo('peoplecount.assignments.*');
    }
}
