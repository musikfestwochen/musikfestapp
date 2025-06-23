<?php

namespace Database\Factories\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
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
        return [
            'vendor' => fake()->company(),
            'model' => fake()->word(),
            'serial' => fake()->unique()->numerify('SN-########'),
        ];
    }

    public function withOrganization(Organization $organization): static
    {
        return $this->state(function (array $attributes) use ($organization): array {
            return [
                'organization_id' => $organization->id,
            ];
        });
    }

    public function withRandomOrganization(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'organization_id' => Organization::query()->inRandomOrder()->first()->id ?? Organization::factory()->create()->id,
            ];
        });
    }
}
