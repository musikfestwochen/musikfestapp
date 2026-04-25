<?php

declare(strict_types=1);

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
            'peoplecount.alerts',
        ];

        $actions = ['create', 'destroy', 'edit', 'index', 'show', 'store', 'update', '*'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::findOrCreate(sprintf('%s.%s', $module, $action));
            }
        }

        // add widget permissions
        Permission::findOrCreate('peoplecount.widgets.active_area_counts');
        Permission::findOrCreate('peoplecount.widgets.sensor_health');
        Permission::findOrCreate('peoplecount.widgets.most_active_sensors');
        Permission::findOrCreate('peoplecount.widgets.area_count_history');

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
            'peoplecount.alerts.*',
            'peoplecount.widgets.active_area_counts',
            'peoplecount.widgets.sensor_health',
            'peoplecount.widgets.most_active_sensors',
            'peoplecount.widgets.area_count_history',
        ]);

        $orgAdminRole = Role::query()->firstOrCreate(['name' => 'OrganizationAdmin']);
        $orgAdminRole->syncPermissions([
            'orgmgmt.users.*',
            'peoplecount.sensors.*',
            'peoplecount.events.*',
            'peoplecount.areas.*',
            'peoplecount.assignments.*',
            'peoplecount.area_resets.*',
            'peoplecount.alerts.*',
            'peoplecount.widgets.active_area_counts',
            'peoplecount.widgets.sensor_health',
            'peoplecount.widgets.most_active_sensors',
            'peoplecount.widgets.area_count_history',
        ]);

        $peoplecountViewerRole = Role::query()->firstOrCreate(['name' => 'PeopleCountViewer']);
        $peoplecountViewerRole->syncPermissions([
            'peoplecount.areas.index',
            'peoplecount.areas.show',
            'peoplecount.events.index',
            'peoplecount.events.show',
            'peoplecount.widgets.active_area_counts',
            'peoplecount.widgets.sensor_health',
            'peoplecount.widgets.most_active_sensors',
            'peoplecount.widgets.area_count_history',
        ]);
    }
}
