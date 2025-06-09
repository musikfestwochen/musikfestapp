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
            Permission::create(['name' => 'users.'.$action]);
            Permission::create(['name' => 'organizations.'.$action]);
        }

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // this can be done as separate statements
        Role::create(['name' => 'Admin'])
            ->givePermissionTo('users.*')
            ->givePermissionTo('organizations.*');
    }
}
