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

        $this->role('SuperAdmin', 'Super administrator', 'Can access and manage everything across all organizations.');

        // create permissions
        $modules = [
            'admin.users',
            'admin.organizations',
            'admin.peoplecount_aggregations',
            'orgmgmt.users',
            'peoplecount.sensors',
            'peoplecount.events',
            'peoplecount.areas',
            'peoplecount.assignments',
            'peoplecount.area_resets',
            'peoplecount.alerts',
            'stage-safety.sensors',
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
        Permission::findOrCreate('stage-safety.monitoring.view');

        // add admin package permissions
        Permission::findOrCreate('admin.logs');
        Permission::findOrCreate('admin.pulse');

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create roles and assign created permissions

        $adminRole = $this->role('Admin', 'Global administrator', 'Can manage users and organizations globally.');
        $adminRole->syncPermissions([
            'admin.users.*',
            'admin.organizations.*',
            'admin.peoplecount_aggregations.*',
            'admin.logs',
            'admin.pulse',
            'orgmgmt.users.*',
            'peoplecount.sensors.*',
            'peoplecount.events.*',
            'peoplecount.areas.*',
            'peoplecount.assignments.*',
            'peoplecount.area_resets.*',
            'peoplecount.alerts.*',
            'stage-safety.sensors.*',
            'stage-safety.monitoring.view',
            'peoplecount.widgets.active_area_counts',
            'peoplecount.widgets.sensor_health',
            'peoplecount.widgets.most_active_sensors',
            'peoplecount.widgets.area_count_history',
        ]);

        $orgAdminRole = $this->role('OrganizationAdmin', 'Organization administrator', 'Has all organization level permissions over all modules.');
        $orgAdminRole->syncPermissions([
            'orgmgmt.users.*',
            'peoplecount.sensors.*',
            'peoplecount.events.*',
            'peoplecount.areas.*',
            'peoplecount.assignments.*',
            'peoplecount.area_resets.*',
            'peoplecount.alerts.*',
            'stage-safety.sensors.*',
            'stage-safety.monitoring.view',
            'peoplecount.widgets.active_area_counts',
            'peoplecount.widgets.sensor_health',
            'peoplecount.widgets.most_active_sensors',
            'peoplecount.widgets.area_count_history',
        ]);

        $peoplecountViewerRole = $this->role('PeopleCountViewer', 'People count viewer', 'Can view people-count dashboards and data.');
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

        $stageSafetyViewerRole = $this->role('StageSafetyViewer', 'Stage Safety viewer', 'Can view Stage Safety monitoring data.');
        $stageSafetyViewerRole->syncPermissions([
            'stage-safety.sensors.index',
            'stage-safety.sensors.show',
            'stage-safety.monitoring.view',
        ]);
    }

    private function role(string $name, string $displayName, string $description): Role
    {
        return Role::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['display_name' => $displayName, 'description' => $description],
        );
    }
}
