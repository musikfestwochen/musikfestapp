<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\AreaSingleReset;
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
        Assignment::factory()->withArea($mfwMainStage)->withSensor($mfwSensors->get(0))->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($mfwMainStage)->withSensor($mfwSensors->get(1))->withDirectionFlipped(true)->create();
        Assignment::factory()->withArea($mfwFoodCourt)->withSensor($mfwSensors->get(2))->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($mfwFoodCourt)->withSensor($mfwSensors->get(3))->withDirectionFlipped(true)->create();
        Assignment::factory()->withArea($mfwVipArea)->withSensor($mfwSensors->get(4))->withDirectionFlipped(false)->create();

        // Assign sensors to MFW Winter Concert areas
        Assignment::factory()->withArea($mfwConcertHall)->withSensor($mfwSensors->get(5))->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($mfwConcertHall)->withSensor($mfwSensors->get(6))->withDirectionFlipped(true)->create();
        Assignment::factory()->withArea($mfwFoyer)->withSensor($mfwSensors->get(7))->withDirectionFlipped(false)->create();

        // Create assignments for ZHAW events using their sensors
        $zhawSensors = \App\Models\Peoplecount\Sensor::query()->where('organization_id', $zhaw->id)->get();

        // Assign sensors to ZHAW Open Day areas
        Assignment::factory()->withArea($zhawAuditorium)->withSensor($zhawSensors->get(0))->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($zhawAuditorium)->withSensor($zhawSensors->get(1))->withDirectionFlipped(true)->create();
        Assignment::factory()->withArea($zhawLabTour)->withSensor($zhawSensors->get(2))->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($zhawLabTour)->withSensor($zhawSensors->get(3))->withDirectionFlipped(true)->create();
        Assignment::factory()->withArea($zhawInfoDesk)->withSensor($zhawSensors->get(4))->withDirectionFlipped(false)->create();

        // Assign sensors to ZHAW Graduation areas
        Assignment::factory()->withArea($zhawGradHall)->withSensor($zhawSensors->get(5))->withDirectionFlipped(false)->create();
        Assignment::factory()->withArea($zhawGradHall)->withSensor($zhawSensors->get(6))->withDirectionFlipped(true)->create();
        Assignment::factory()->withArea($zhawReception)->withSensor($zhawSensors->get(7))->withDirectionFlipped(false)->create();

        // Create sample manual resets (AreaSingleReset) with different timestamps
        // Historical resets for MFW Summer Festival areas
        AreaSingleReset::factory()->withArea($mfwMainStage)->withCreatedBy($mfwUsers->first())->withResetValue(0)->withEffectiveAt(new \DateTime('2024-08-15 09:00:00'))->create([
            'notes' => 'Event start - reset to zero for opening',
        ]);
        AreaSingleReset::factory()->withArea($mfwFoodCourt)->withCreatedBy($mfwUsers->get(1))->withResetValue(50)->withEffectiveAt(new \DateTime('2024-08-16 12:00:00'))->create([
            'notes' => 'Lunch rush adjustment - technical issue resolved',
        ]);
        AreaSingleReset::factory()->withArea($mfwVipArea)->withCreatedBy($mfwUsers->get(2))->withResetValue(25)->withEffectiveAt(new \DateTime('2024-08-17 18:00:00'))->create([
            'notes' => 'VIP reception start - manual count verification',
        ]);

        // Recent resets for MFW Winter Concert areas
        AreaSingleReset::factory()->withArea($mfwConcertHall)->withCreatedBy($mfwUsers->get(3))->withResetValue(0)->withEffectiveAt(new \DateTime('2024-12-20 17:30:00'))->create([
            'notes' => 'Pre-concert reset - venue preparation complete',
        ]);
        AreaSingleReset::factory()->withArea($mfwFoyer)->withCreatedBy($mfwUsers->get(4))->withResetValue(15)->withEffectiveAt(new \DateTime('2024-12-21 19:45:00'))->create([
            'notes' => 'Intermission adjustment - sensor calibration',
        ]);

        // Sample resets for ZHAW events
        AreaSingleReset::factory()->withArea($zhawAuditorium)->withCreatedBy($zhawUsers->first())->withResetValue(0)->withEffectiveAt(new \DateTime('2024-09-14 08:30:00'))->create([
            'notes' => 'Open Day preparation - venue cleared',
        ]);
        AreaSingleReset::factory()->withArea($zhawLabTour)->withCreatedBy($zhawUsers->get(1))->withResetValue(10)->withEffectiveAt(new \DateTime('2024-09-14 14:00:00'))->create([
            'notes' => 'Afternoon tour group - manual count correction',
        ]);
        AreaSingleReset::factory()->withArea($zhawGradHall)->withCreatedBy($zhawUsers->get(2))->withResetValue(0)->withEffectiveAt(new \DateTime('2024-10-25 13:30:00'))->create([
            'notes' => 'Graduation ceremony setup - final preparation',
        ]);

        // Create sample recurring resets (AreaRecurringReset) with various RRULE patterns
        // Daily resets for main stage during festival
        AreaRecurringReset::factory()->withArea($mfwMainStage)->withEvent($mfwSummerFest)->daily()->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Daily morning reset at 6 AM during festival',
            'rrule' => 'FREQ=DAILY;INTERVAL=1;BYHOUR=6;BYMINUTE=0',
        ]);

        // Weekly resets for food court (every Monday)
        AreaRecurringReset::factory()->withArea($mfwFoodCourt)->withEvent($mfwSummerFest)->weekly()->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Weekly deep cleaning reset - every Monday at 5 AM',
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO;BYHOUR=5;BYMINUTE=0',
        ]);

        // Bi-daily resets for VIP area
        AreaRecurringReset::factory()->withArea($mfwVipArea)->withEvent($mfwSummerFest)->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Bi-daily VIP area reset - morning and evening',
            'rrule' => 'FREQ=DAILY;INTERVAL=1;BYHOUR=6,18;BYMINUTE=0',
        ]);

        // Monthly resets for concert hall
        AreaRecurringReset::factory()->withArea($mfwConcertHall)->withEvent($mfwWinterConcert)->monthly()->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Monthly maintenance reset - first day of month',
            'rrule' => 'FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=1;BYHOUR=7;BYMINUTE=0',
        ]);

        // Custom pattern for ZHAW auditorium (Tuesday and Thursday)
        AreaRecurringReset::factory()->withArea($zhawAuditorium)->withEvent($zhawOpenDay)->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Lecture hall reset - Tuesday and Thursday mornings',
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=TU,TH;BYHOUR=7;BYMINUTE=30',
        ]);

        // Weekend resets for lab tour area
        AreaRecurringReset::factory()->withArea($zhawLabTour)->withEvent($zhawOpenDay)->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Weekend preparation reset - Saturday mornings',
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=SA;BYHOUR=8;BYMINUTE=0',
        ]);

        // Event-specific reset for graduation hall
        AreaRecurringReset::factory()->withArea($zhawGradHall)->withEvent($zhawGraduation)->withResetValue(0)->withTimezone('Europe/Zurich')->create([
            'notes' => 'Pre-ceremony reset - 2 hours before each graduation event',
            'rrule' => 'FREQ=YEARLY;INTERVAL=1;BYMONTH=10;BYMONTHDAY=25;BYHOUR=12;BYMINUTE=0',
        ]);
    }
}
