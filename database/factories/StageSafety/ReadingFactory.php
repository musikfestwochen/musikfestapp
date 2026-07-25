<?php

declare(strict_types=1);

namespace Database\Factories\StageSafety;

use App\Enums\StageSafety\ReadingKind;
use App\Models\StageSafety\Reading;
use App\Models\StageSafety\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reading>
 */
class ReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $observedAt = fake()->dateTimeBetween('-1 hour');

        return [
            'sensor_id' => Sensor::factory(),
            'kind' => fake()->randomElement(ReadingKind::cases()),
            'value' => fake()->randomFloat(7, 0, 40),
            'unit' => 'm/s',
            'observed_at' => $observedAt,
            'received_at' => fake()->dateTimeBetween($observedAt),
            'window_seconds' => fake()->optional()->randomElement([0, 10, 60]),
            'battery_low' => fake()->optional()->boolean(5),
            'rssi_dbm' => fake()->optional()->numberBetween(-98, -30),
            'cv' => fake()->optional()->numberBetween(55, 110),
        ];
    }
}
