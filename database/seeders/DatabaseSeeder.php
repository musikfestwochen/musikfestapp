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

        // create organizations (MFW and ZHAW)
        $mfw = Organization::factory()->create([
            'name' => 'Winterthurer Musikfestwochen',
            'slug' => 'mfw',
        ]);
        $zhaw = Organization::factory()->create([
            'name' => 'Zürcher Hochschule für Angewandte Wissenschaften',
            'slug' => 'zhaw',
        ]);

        User::factory()->globalAdmin()->organizationAdmin(null, [$mfw, $zhaw])->create([
            'name' => 'Simon',
            'email' => 'simon@musikfestapp.ch',
        ]);

        User::factory()->globalAdmin()->organizationAdmin($mfw)->create([
            'name' => 'Pirmin',
            'email' => 'pirmin@musikfestapp.ch',
        ]);

        User::factory()->organizationAdmin($mfw)->create([
            'name' => 'Lotta',
            'email' => 'lotta@musikfestwochen.ch',
        ]);

        Organization::factory(10)->create();
        Organization::factory(3)->deleted()->create();
        User::factory(20)->randomVerified()->withOrganizations()->create();
        Sensor::factory(100)->withRandomOrganization()->create();
    }
}
