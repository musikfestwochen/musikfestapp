<?php

namespace Database\Factories\Peoplecount;

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => AlertType::OccupancyAlert,
            'channel' => fake()->randomElement([AlertChannel::Vonage, AlertChannel::Email]),
            'cooldown_seconds' => fake()->numberBetween(60, 3600),
            'created_by' => User::factory(),
            'occupancy_alert_threshold' => fake()->optional()->numberBetween(10, 10000),
        ];
    }
}
