<?php

namespace Database\Factories\Peoplecount;

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
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
            'area_id' => Area::factory(),
            'type' => AlertType::OccupancyAlert,
            'channel' => fake()->randomElement([AlertChannel::Vonage, AlertChannel::Email]),
            'cooldown_minutes' => fake()->numberBetween(30, 360),
            'created_by' => User::factory(),
            'occupancy_alert_threshold' => fake()->optional()->numberBetween(10, 10000),
        ];
    }
}
