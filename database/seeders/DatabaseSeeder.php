<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
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

        // Create single organization: Winterthurer Musikfestwochen
        $mfw = Organization::factory()->create([
            'name' => 'Winterthurer Musikfestwochen',
            'slug' => 'mfw',
        ]);

        // Create admin users for the organization
        User::factory()->globalAdmin()->organizationAdmin($mfw)->create([
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

        // Create single event: Musikfestwochen 2025 (6th August 2025 6pm to 17th August 2025 10pm)
        $event = Event::factory()->withOrganization($mfw)->create([
            'name' => 'Musikfestwochen 2025',
            'starts_at' => '2025-08-06 18:00:00', // 6pm UTC
            'ends_at' => '2025-08-17 22:00:00',   // 10pm UTC
        ]);

        // Create single area: Gesamtes Gelände
        $area = Area::factory()->withEvent($event)->create([
            'name' => 'Gesamtes Gelände',
        ]);

        // Create 10 sensors for the organization
        $sensors = Sensor::factory(10)->axisP88152()->withToken()->withOrganization($mfw)->create();

        // Assign all 10 sensors to the area with random direction flipping
        foreach ($sensors as $sensor) {
            Assignment::factory()
                ->withArea($area)
                ->withSensor($sensor)
                ->withDirectionFlipped(fake()->boolean())
                ->create();
        }
    }
}
