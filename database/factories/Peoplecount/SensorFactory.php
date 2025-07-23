<?php

namespace Database\Factories\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
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
            'organization_id' => Organization::query()->inRandomOrder()->first()->id ?? Organization::factory()->create()->id,
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

    /**
     * Configure the model factory to create a sensor with a valid API token.
     */
    public function withToken(): static
    {
        return $this->afterCreating(function (Sensor $sensor) {
            $sensorService = new SensorService;
            $token = $sensorService->createOrRegenerateToken($sensor);
            $sensor->api_token = $token;
            $sensor->save();
        });
    }

    /**
     * Configure the model factory to create an Axis P8815-2 sensor.
     */
    public function axisP88152(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'vendor' => 'Axis',
                'model' => 'P8815-2',
            ];
        });
    }
}
