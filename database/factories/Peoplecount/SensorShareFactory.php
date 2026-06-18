<?php

declare(strict_types=1);

namespace Database\Factories\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SensorShare>
 */
class SensorShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ownerOrganization = Organization::query()->inRandomOrder()->first() ?? Organization::factory()->create();
        $borrowerOrganization = Organization::query()->where('id', '!=', $ownerOrganization->id)->inRandomOrder()->first()
            ?? Organization::factory()->create();
        $sensor = Sensor::query()->where('organization_id', $ownerOrganization->id)->inRandomOrder()->first()
            ?? Sensor::factory()->withOrganization($ownerOrganization)->create();

        return [
            'sensor_id' => $sensor->id,
            'owner_organization_id' => $sensor->organization_id,
            'borrower_organization_id' => $borrowerOrganization->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ];
    }

    public function withSensor(Sensor $sensor): static
    {
        return $this->state(function (array $attributes) use ($sensor): array {
            return [
                'sensor_id' => $sensor->id,
                'owner_organization_id' => $sensor->organization_id,
            ];
        });
    }

    public function withBorrowerOrganization(Organization $organization): static
    {
        return $this->state(function (array $attributes) use ($organization): array {
            return [
                'borrower_organization_id' => $organization->id,
            ];
        });
    }
}
