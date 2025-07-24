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

        // Create realistic events for MFW (Winterthurer Musikfestwochen)
        $mfwSummerFest = Event::factory()->withOrganization($mfw)->create([
            'name' => 'Winterthurer Musikfestwochen 2024 - Sommerfest',
            'starts_at' => '2024-08-15 10:00:00',
            'ends_at' => '2024-08-18 23:00:00',
        ]);

        $mfwWinterConcert = Event::factory()->withOrganization($mfw)->create([
            'name' => 'Winterthurer Musikfestwochen 2024 - Winterkonzert',
            'starts_at' => '2024-12-20 18:00:00',
            'ends_at' => '2024-12-22 22:00:00',
        ]);

        // Create realistic events for ZHAW (Zürcher Hochschule für Angewandte Wissenschaften)
        $zhawOpenDay = Event::factory()->withOrganization($zhaw)->create([
            'name' => 'ZHAW Open Day 2024 - Campus Winterthur',
            'starts_at' => '2024-09-14 09:00:00',
            'ends_at' => '2024-09-14 17:00:00',
        ]);

        $zhawGraduation = Event::factory()->withOrganization($zhaw)->create([
            'name' => 'ZHAW Graduation Ceremony 2024',
            'starts_at' => '2024-10-25 14:00:00',
            'ends_at' => '2024-10-25 18:00:00',
        ]);

        // Create realistic areas for MFW Summer Festival
        $mfwMainStage = Area::factory()->withEvent($mfwSummerFest)->create([
            'name' => 'Hauptbühne',
        ]);
        $mfwFoodCourt = Area::factory()->withEvent($mfwSummerFest)->create([
            'name' => 'Food Court',
        ]);
        $mfwVipArea = Area::factory()->withEvent($mfwSummerFest)->create([
            'name' => 'VIP Bereich',
        ]);

        // Create realistic areas for MFW Winter Concert
        $mfwConcertHall = Area::factory()->withEvent($mfwWinterConcert)->create([
            'name' => 'Konzertsaal',
        ]);
        $mfwFoyer = Area::factory()->withEvent($mfwWinterConcert)->create([
            'name' => 'Foyer',
        ]);

        // Create realistic areas for ZHAW Open Day
        $zhawAuditorium = Area::factory()->withEvent($zhawOpenDay)->create([
            'name' => 'Hauptauditorium',
        ]);
        $zhawLabTour = Area::factory()->withEvent($zhawOpenDay)->create([
            'name' => 'Labor-Rundgang',
        ]);
        $zhawInfoDesk = Area::factory()->withEvent($zhawOpenDay)->create([
            'name' => 'Informationsstand',
        ]);

        // Create realistic areas for ZHAW Graduation
        $zhawGradHall = Area::factory()->withEvent($zhawGraduation)->create([
            'name' => 'Graduierungshalle',
        ]);
        $zhawReception = Area::factory()->withEvent($zhawGraduation)->create([
            'name' => 'Empfangsbereich',
        ]);

        // Create assignments for MFW events using their sensors
        $mfwSensors = \App\Models\Peoplecount\Sensor::query()->where('organization_id', $mfw->id)->get();

        // Assign sensors to MFW Summer Festival areas
        Assignment::factory()->withArea($mfwMainStage)->withSensor($mfwSensors->get(0))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
        Assignment::factory()->withArea($mfwMainStage)->withSensor($mfwSensors->get(1))->withDirection(\App\Enums\Peoplecount\Direction::OUT)->create();
        Assignment::factory()->withArea($mfwFoodCourt)->withSensor($mfwSensors->get(2))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
        Assignment::factory()->withArea($mfwFoodCourt)->withSensor($mfwSensors->get(3))->withDirection(\App\Enums\Peoplecount\Direction::OUT)->create();
        Assignment::factory()->withArea($mfwVipArea)->withSensor($mfwSensors->get(4))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();

        // Assign sensors to MFW Winter Concert areas
        Assignment::factory()->withArea($mfwConcertHall)->withSensor($mfwSensors->get(5))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
        Assignment::factory()->withArea($mfwConcertHall)->withSensor($mfwSensors->get(6))->withDirection(\App\Enums\Peoplecount\Direction::OUT)->create();
        Assignment::factory()->withArea($mfwFoyer)->withSensor($mfwSensors->get(7))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();

        // Create assignments for ZHAW events using their sensors
        $zhawSensors = \App\Models\Peoplecount\Sensor::query()->where('organization_id', $zhaw->id)->get();

        // Assign sensors to ZHAW Open Day areas
        Assignment::factory()->withArea($zhawAuditorium)->withSensor($zhawSensors->get(0))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
        Assignment::factory()->withArea($zhawAuditorium)->withSensor($zhawSensors->get(1))->withDirection(\App\Enums\Peoplecount\Direction::OUT)->create();
        Assignment::factory()->withArea($zhawLabTour)->withSensor($zhawSensors->get(2))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
        Assignment::factory()->withArea($zhawLabTour)->withSensor($zhawSensors->get(3))->withDirection(\App\Enums\Peoplecount\Direction::OUT)->create();
        Assignment::factory()->withArea($zhawInfoDesk)->withSensor($zhawSensors->get(4))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();

        // Assign sensors to ZHAW Graduation areas
        Assignment::factory()->withArea($zhawGradHall)->withSensor($zhawSensors->get(5))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
        Assignment::factory()->withArea($zhawGradHall)->withSensor($zhawSensors->get(6))->withDirection(\App\Enums\Peoplecount\Direction::OUT)->create();
        Assignment::factory()->withArea($zhawReception)->withSensor($zhawSensors->get(7))->withDirection(\App\Enums\Peoplecount\Direction::IN)->create();
    }
}
