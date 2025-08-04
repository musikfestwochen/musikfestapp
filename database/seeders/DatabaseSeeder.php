<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
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

        // Create single organization: Winterthurer Musikfestwochen
        $mfw = Organization::factory()->create([
            'name' => 'Winterthurer Musikfestwochen',
            'slug' => 'mfw',
        ]);

        // Create admin users for the organization
        User::factory()->globalAdmin()->organizationAdmin($mfw)->create([
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

        // Create preparation event: Musikfestwochen Vorbereitung (1st August 2025 8am to 6th August 2025 6pm)
        $preparationEvent = Event::factory()->withOrganization($mfw)->create([
            'name' => 'Musikfestwochen Vorbereitung',
            'starts_at' => '2025-08-05 05:00:00', // 5am UTC
            'ends_at' => '2025-08-06 16:00:00',   // 4pm UTC
        ]);

        // Create main event: Musikfestwochen 2025 (6th August 2025 6pm to 17th August 2025 10pm)
        $event = Event::factory()->withOrganization($mfw)->create([
            'name' => 'Musikfestwochen 2025',
            'starts_at' => '2025-08-06 16:00:00', // 4pm UTC
            'ends_at' => '2025-08-17 21:00:00',   // 9pm UTC
        ]);

        // Create area for preparation event: Gesamtes Gelände
        Area::factory()->withEvent($preparationEvent)->create([
            'name' => 'Gesamtes Gelände (Vorbereitung)',
        ]);

        // Create area for main event: Gesamtes Gelände
        Area::factory()->withEvent($event)->create([
            'name' => 'Gesamtes Gelände',
        ]);
    }
}
