<?php

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaRecurringReset>
 */
class AreaRecurringResetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timezones = [
            'Europe/Zurich',
            'UTC',
            'Europe/Berlin',
            'America/New_York',
            'Asia/Tokyo',
        ];

        return [
            'area_id' => Area::query()->inRandomOrder()->first()->id ?? Area::factory()->create()->id,
            'reset_value' => fake()->numberBetween(0, 1000),
            'reset_time' => fake()->time('H:i'),
            'timezone' => fake()->randomElement($timezones),
            'notes' => fake()->optional(0.7)->sentence(),
        ];
    }

    /**
     * Configure the model factory to use a specific area.
     */
    public function withArea(Area $area): static
    {
        return $this->state(function (array $attributes) use ($area): array {
            return [
                'area_id' => $area->id,
            ];
        });
    }

    /**
     * Configure the model factory to create a reset with a specific value.
     */
    public function withResetValue(int $value): static
    {
        return $this->state(function (array $attributes) use ($value): array {
            return [
                'reset_value' => $value,
            ];
        });
    }

    /**
     * Configure the model factory to use a specific reset time.
     */
    public function withResetTime(string $resetTime): static
    {
        return $this->state(function (array $attributes) use ($resetTime): array {
            return [
                'reset_time' => $resetTime,
            ];
        });
    }

    /**
     * Configure the model factory to use a specific timezone.
     */
    public function withTimezone(string $timezone): static
    {
        return $this->state(function (array $attributes) use ($timezone): array {
            return [
                'timezone' => $timezone,
            ];
        });
    }
}
