<?php

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Assignment;
use App\Models\Peoplecount\Event;
use App\Models\Peoplecount\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = Event::query()->inRandomOrder()->first() ?? Event::factory()->create();
        $area = Area::query()->where('event_id', $event->id)->inRandomOrder()->first()
            ?? Area::factory()->withEvent($event)->create();

        // Start and end times within the event's time boundaries
        $activeFrom = fake()->dateTimeBetween($event->starts_at, $event->ends_at);
        $activeTo = fake()->dateTimeBetween($activeFrom, $event->ends_at);

        return [
            'event_id' => $event->id,
            'area_id' => $area->id,
            'sensor_id' => Sensor::query()->inRandomOrder()->first()->id ?? Sensor::factory()->create()->id,
            'direction_flipped' => fake()->boolean(),
            'active_from' => $activeFrom,
            'active_to' => $activeTo,
        ];
    }

    /**
     * Configure the model factory to use a specific event.
     */
    public function withEvent(Event $event): static
    {
        return $this->state(function (array $attributes) use ($event): array {
            // If we're changing the event, we need to ensure the area belongs to this event
            $area = Area::query()->where('event_id', $event->id)->inRandomOrder()->first()
                ?? Area::factory()->withEvent($event)->create();

            // Start and end times within the event's time boundaries
            $activeFrom = fake()->dateTimeBetween($event->starts_at, $event->ends_at);
            $activeTo = fake()->dateTimeBetween($activeFrom, $event->ends_at);

            return [
                'event_id' => $event->id,
                'area_id' => $area->id,
                'active_from' => $activeFrom,
                'active_to' => $activeTo,
            ];
        });
    }

    /**
     * Configure the model factory to use a specific area.
     */
    public function withArea(Area $area): static
    {
        return $this->state(function (array $attributes) use ($area): array {
            $event = $area->event;

            // Start and end times within the event's time boundaries
            $activeFrom = fake()->dateTimeBetween($event->starts_at, $event->ends_at);
            $activeTo = fake()->dateTimeBetween($activeFrom, $event->ends_at);

            return [
                'event_id' => $event->id,
                'area_id' => $area->id,
                'active_from' => $activeFrom,
                'active_to' => $activeTo,
            ];
        });
    }

    /**
     * Configure the model factory to use a specific sensor.
     */
    public function withSensor(Sensor $sensor): static
    {
        return $this->state(function (array $attributes) use ($sensor): array {
            return [
                'sensor_id' => $sensor->id,
            ];
        });
    }

    /**
     * Configure the model factory to use a specific direction_flipped value.
     */
    public function withDirectionFlipped(bool $directionFlipped): static
    {
        return $this->state(function (array $attributes) use ($directionFlipped): array {
            return [
                'direction_flipped' => $directionFlipped,
            ];
        });
    }
}
