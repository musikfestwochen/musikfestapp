<?php

declare(strict_types=1);

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaSingleReset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaSingleReset>
 */
class AreaSingleResetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area_id' => Area::query()->inRandomOrder()->first()->id ?? Area::factory()->create()->id,
            'reset_value' => fake()->numberBetween(0, 1000),
            'effective_at' => fake()->dateTimeBetween('-1 month', '+1 week'),
            'created_by' => User::query()->inRandomOrder()->first()->id ?? User::factory()->create()->id,
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
     * Configure the model factory to use a specific user as creator.
     */
    public function withCreatedBy(User $user): static
    {
        return $this->state(function (array $attributes) use ($user): array {
            return [
                'created_by' => $user->id,
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
     * Configure the model factory to create a reset effective at a specific time.
     */
    public function withEffectiveAt(\DateTimeInterface $effectiveAt): static
    {
        return $this->state(function (array $attributes) use ($effectiveAt): array {
            return [
                'effective_at' => $effectiveAt,
            ];
        });
    }
}
