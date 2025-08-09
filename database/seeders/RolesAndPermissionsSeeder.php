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

        Role::query()->firstOrCreate(['name' => 'SuperAdmin']);

        // create permissions
        $modules = [
            'admin.users',
            'admin.organizations',
            'orgmgmt.users',
            'peoplecount.sensors',
            'peoplecount.events',
            'peoplecount.areas',
            'peoplecount.assignments',
            'peoplecount.area_resets',
        ];

        $actions = ['create', 'destroy', 'edit', 'index', 'show', 'store', 'update', '*'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::findOrCreate(sprintf('%s.%s', $module, $action));
            }
        }

        // add widget permissions
        Permission::findOrCreate('peoplecount.widgets.activeareacounts');

        // add admin.pulse permission
        Permission::findOrCreate('admin.pulse');

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create roles and assign created permissions

        $adminRole = Role::query()->firstOrCreate(['name' => 'Admin']);
        $adminRole->syncPermissions([
            'admin.users.*',
            'admin.organizations.*',
            'admin.pulse',
            'orgmgmt.users.*',
            'peoplecount.sensors.*',
            'peoplecount.events.*',
            'peoplecount.areas.*',
            'peoplecount.assignments.*',
            'peoplecount.area_resets.*',
            'peoplecount.widgets.activeareacounts',
        ]);

        $orgAdminRole = Role::query()->firstOrCreate(['name' => 'OrganizationAdmin']);
        $orgAdminRole->syncPermissions([
            'orgmgmt.users.*',
            'peoplecount.sensors.*',
            'peoplecount.events.*',
            'peoplecount.areas.*',
            'peoplecount.assignments.*',
            'peoplecount.area_resets.*',
            'peoplecount.widgets.activeareacounts',
        ]);

        $peoplecountViewerRole = Role::query()->firstOrCreate(['name' => 'PeopleCountViewer']);
        $peoplecountViewerRole->syncPermissions([
            'peoplecount.areas.index',
            'peoplecount.areas.show',
            'peoplecount.events.index',
            'peoplecount.events.show',
            'peoplecount.widgets.activeareacounts',
        ]);
    }
}
