<?php

namespace Database\Factories\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', '+2 months');
        $endsAt = Carbon::instance($startsAt)->addDays(fake()->numberBetween(1, 7));

        return [
            'name' => fake()->sentence(3),
            'organization_id' => Organization::query()->inRandomOrder()->first()->id ?? Organization::factory()->create()->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * Configure the model factory to use a specific organization.
     */
    public function withOrganization(Organization $organization): static
    {
        return $this->state(function (array $attributes) use ($organization): array {
            return [
                'organization_id' => $organization->id,
            ];
        });
    }

    /**
     * Configure the model factory to use a random organization.
     */
    public function withRandomOrganization(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'organization_id' => Organization::query()->inRandomOrder()->first()->id ?? Organization::factory()->create()->id,
            ];
        });
    }

    /**
     * Configure the model factory to create 1-3 areas for each event.
     *
     * @param  int|null  $count  The number of areas to create. If null, a random number between 1 and 3 will be used.
     */
    public function withAreas(?int $count = null): static
    {
        return $this->afterCreating(function (Event $event) use ($count) {
            $areasCount = $count ?? fake()->numberBetween(1, 3);
            Area::factory($areasCount)->withEvent($event)->create();
        });
    }
}
