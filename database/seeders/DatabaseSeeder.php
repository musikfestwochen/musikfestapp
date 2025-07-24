<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Peoplecount\Event;
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
            'phone' => '+41797164443',
        ]);

        User::factory()->globalAdmin()->organizationAdmin($mfw)->create([
            'name' => 'Pirmin',
            'email' => 'pirmin@musikfestapp.ch',
            'phone' => '+41765021392',
        ]);

        User::factory()->organizationAdmin($mfw)->create([
            'name' => 'Lotta',
            'email' => 'lotta@musikfestwochen.ch',
            'phone' => '+41794256763',
        ]);

        // Create 5 random users for each organization
        $mfwUsers = User::factory(5)->randomVerified()->create();
        foreach ($mfwUsers as $user) {
            $user->organizations()->attach($mfw->id);
        }

        $zhawUsers = User::factory(5)->randomVerified()->create();
        foreach ($zhawUsers as $user) {
            $user->organizations()->attach($zhaw->id);
        }

        // Create 10 random sensors for each organization
        Sensor::factory(10)->axisP88152()->withToken()->withOrganization($mfw)->create();
        Sensor::factory(10)->axisP88152()->withToken()->withOrganization($zhaw)->create();

        // Create 2 random events for each organization with 1-3 areas each
        Event::factory(2)->withOrganization($mfw)->withAreas()->create();
        Event::factory(2)->withOrganization($zhaw)->withAreas()->create();
    }
}
