<?php

declare(strict_types=1);

namespace Database\Factories\StageSafety;

use App\Enums\StageSafety\SensorType;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sensor>
 */
class SensorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sensorType = SensorType::BroadweighBwWss;

        return [
            'organization_id' => Organization::factory(),
            'manufacturer' => $sensorType->manufacturer(),
            'model' => $sensorType->model(),
            'identifier' => sprintf('%06X', fake()->unique()->numberBetween(0, 0xFFFFFF)),
            'name' => fake()->optional()->words(3, true),
            'location' => fake()->optional()->streetName(),
            'stale_after_seconds' => 300,
        ];
    }
}
