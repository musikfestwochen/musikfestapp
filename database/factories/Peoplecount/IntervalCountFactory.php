<?php

declare(strict_types=1);

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\IntervalCount;
use App\Models\Peoplecount\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntervalCount>
 */
class IntervalCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sensor_id' => Sensor::query()->inRandomOrder()->first()->id ?? Sensor::factory()->create()->id,
            'ts_from' => now()->subMinute(),
            'ts_to' => now(),
            'received_at' => fn (array $attributes) => $attributes['ts_to'],
            'count_in' => fake()->numberBetween(0, 100),
            'count_out' => fake()->numberBetween(0, 100),
        ];
    }
}
