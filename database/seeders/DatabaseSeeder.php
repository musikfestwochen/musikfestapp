<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $rolePermissionsSeeder = new RolesAndPermissionsSeeder;
        $rolePermissionsSeeder->run();

        User::factory()->globalAdmin()->create([
            'name' => 'Simon',
            'email' => 'simon@musikfestapp.ch',
        ]);

        User::factory()->globalAdmin()->create([
            'name' => 'Pirmin',
            'email' => 'pirmin@musikfestapp.ch',
        ]);

        Organization::factory(10)->create();
        Organization::factory(3)->deleted()->create();
        User::factory(20)->randomVerified()->withOrganizations()->create();
        Sensor::factory(10)->withRandomOrganization()->create();
    }
}
