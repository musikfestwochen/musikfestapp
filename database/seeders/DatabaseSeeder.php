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
use Illuminate\Support\Facades\Date;

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
            'starts_at' => '2026-05-20 05:00:00', // 5am UTC
            'ends_at' => '2026-05-28 23:45:00',   // 4pm UTC
        ]);

        // Create main event: Musikfestwochen 2025 (6th August 2025 6pm to 17th August 2025 10pm)
        $event = Event::factory()->withOrganization($mfw)->create([
            'name' => 'Musikfestwochen 2025',
            'starts_at' => '2025-08-06 16:00:00', // 4pm UTC
            'ends_at' => '2025-08-17 21:00:00',   // 9pm UTC
        ]);

        // Create area for preparation event: Gesamtes Gelände
        $prepArea = Area::factory()->withEvent($preparationEvent)->create([
            'name' => 'Gesamtes Gelände (Vorbereitung)',
        ]);

        // Create area for main event: Gesamtes Gelände
        $mainArea = Area::factory()->withEvent($event)->create([
            'name' => 'Gesamtes Gelände',
        ]);

        // Create two sensors and assign them to both areas
        $sensorA = Sensor::factory()->withOrganization($mfw)->axisP88152()->withToken()->create();
        $sensorB = Sensor::factory()->withOrganization($mfw)->axisP88152()->withToken()->create();

        // Assign both sensors to the preparation area (within prep event timeframe)
        Assignment::factory()->withArea($prepArea)->withSensor($sensorA)->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($prepArea)->withSensor($sensorB)->withDirectionFlipped(false)->create();

        // Assign both sensors to the main event area (within main event timeframe)
        Assignment::factory()->withArea($mainArea)->withSensor($sensorA)->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($mainArea)->withSensor($sensorB)->withDirectionFlipped(false)->create();

        // ----------------------------------------
        // Additional demo data for local testing
        // ----------------------------------------

        // Create two random organizations with one event each
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $now = Date::now('UTC');

        // Event for first organization: 1 area
        $event1 = Event::factory()->withOrganization($org1)->create([
            'name' => sprintf('%s Event', $org1->name),
            'starts_at' => $now->copy()->subDay()->startOfHour(),
            'ends_at' => $now->copy()->addDays(3)->endOfHour(),
        ]);
        $area1 = Area::factory()->withEvent($event1)->create(['name' => 'Main Area']);

        // Event for second organization: 2 areas
        $event2 = Event::factory()->withOrganization($org2)->create([
            'name' => sprintf('%s Event', $org2->name),
            'starts_at' => $now->copy()->subDay()->startOfHour(),
            'ends_at' => $now->copy()->addDays(3)->endOfHour(),
        ]);
        $areas2 = Area::factory()->withEvent($event2)->count(2)->create();

        // Create a bunch of Axis sensors per event
        $sensors1 = Sensor::factory()
            ->withOrganization($org1)
            ->axisP88152()
            ->withToken()
            ->count(random_int(3, 6))
            ->create();

        $sensors2 = Sensor::factory()
            ->withOrganization($org2)
            ->axisP88152()
            ->withToken()
            ->count(random_int(3, 6))
            ->create();

        // Assign sensors during the whole period, randomly flipped
        foreach ($sensors1 as $sensor) {
            Assignment::factory()->withArea($area1)->withSensor($sensor)->create([
                'event_id' => $event1->id,
                'direction_flipped' => (bool) random_int(0, 1),
                'active_from' => $event1->starts_at,
                'active_to' => $event1->ends_at,
            ]);
        }

        $areas2List = $areas2->values();
        foreach ($sensors2 as $index => $sensor) {
            $area = $areas2List->get($index % $areas2List->count());
            Assignment::factory()->withArea($area)->withSensor($sensor)->create([
                'event_id' => $event2->id,
                'direction_flipped' => (bool) random_int(0, 1),
                'active_from' => $event2->starts_at,
                'active_to' => $event2->ends_at,
            ]);
        }

        // Add a past event for variety
        $pastEvent = Event::factory()->withOrganization($org1)->create([
            'name' => sprintf('%s Past Event', $org1->name),
            'starts_at' => $now->copy()->subDays(20)->startOfHour(),
            'ends_at' => $now->copy()->subDays(10)->endOfHour(),
        ]);
        $pastArea = Area::factory()->withEvent($pastEvent)->create(['name' => 'Past Area']);
        $pastSensors = Sensor::factory()->withOrganization($org1)->axisP88152()->withToken()->count(random_int(2, 3))->create();
        foreach ($pastSensors as $sensor) {
            // Full-span assignment
            Assignment::factory()->withArea($pastArea)->withSensor($sensor)->create([
                'event_id' => $pastEvent->id,
                'direction_flipped' => (bool) random_int(0, 1),
                'active_from' => $pastEvent->starts_at,
                'active_to' => $pastEvent->ends_at,
            ]);

            // Optional: partial assignment inside the event window
            $partialFrom = $pastEvent->starts_at->copy()->addDays(random_int(1, 3));
            $partialTo = $partialFrom->copy()->addDays(random_int(1, 2));
            if ($partialTo->lessThan($pastEvent->ends_at)) {
                Assignment::factory()->withArea($pastArea)->withSensor($sensor)->create([
                    'event_id' => $pastEvent->id,
                    'direction_flipped' => (bool) random_int(0, 1),
                    'active_from' => $partialFrom,
                    'active_to' => $partialTo,
                ]);
            }
        }

        // Randomly generate a few users with random roles (keeping Simon and Pirmin above)
        $orgPool = collect([$mfw, $org1, $org2]);
        for ($i = 0; $i < 6; $i++) {
            $role = ['Admin', 'OrganizationAdmin', 'PeopleCountViewer'][random_int(0, 2)];
            if ($role === 'Admin') {
                User::factory()->globalAdmin()->create();
            } elseif ($role === 'OrganizationAdmin') {
                $org = $orgPool->random();
                User::factory()->organizationAdmin($org)->create();
            } else {
                $org = $orgPool->random();
                $user = User::factory()->create();
                $user->organizations()->attach($org->id);
                setPermissionsOrgId($org->id);
                $user->assignRole('PeopleCountViewer');
            }
        }
    }
}
