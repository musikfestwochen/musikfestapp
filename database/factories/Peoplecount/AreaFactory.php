<?php

declare(strict_types=1);

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'event_id' => Event::query()->inRandomOrder()->first()->id ?? Event::factory()->create()->id,
        ];
    }

    /**
     * Configure the model factory to use a specific event.
     */
    public function withEvent(Event $event): static
    {
        return $this->state(function (array $attributes) use ($event): array {
            return [
                'event_id' => $event->id,
            ];
        });
    }

    /**
     * Configure the model factory to use a random event.
     */
    public function withRandomEvent(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'event_id' => Event::query()->inRandomOrder()->first()->id ?? Event::factory()->create()->id,
            ];
        });
    }
}
