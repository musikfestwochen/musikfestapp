<?php

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Models\Peoplecount\Event;
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
        $rrulePatterns = [
            'FREQ=DAILY;INTERVAL=1',
            'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
            'FREQ=WEEKLY;INTERVAL=1;BYDAY=TU,TH',
            'FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=1',
            'FREQ=DAILY;INTERVAL=2',
            'FREQ=WEEKLY;INTERVAL=2;BYDAY=WE',
        ];

        $timezones = [
            'Europe/Zurich',
            'UTC',
            'Europe/Berlin',
            'America/New_York',
            'Asia/Tokyo',
        ];

        return [
            'area_id' => Area::query()->inRandomOrder()->first()->id ?? Area::factory()->create()->id,
            'event_id' => Event::query()->inRandomOrder()->first()->id ?? Event::factory()->create()->id,
            'reset_value' => fake()->numberBetween(0, 1000),
            'rrule' => fake()->randomElement($rrulePatterns),
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
     * Configure the model factory to use a specific RRULE.
     */
    public function withRRule(string $rrule): static
    {
        return $this->state(function (array $attributes) use ($rrule): array {
            return [
                'rrule' => $rrule,
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

    /**
     * Configure the model factory to create a daily recurring reset.
     */
    public function daily(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'rrule' => 'FREQ=DAILY;INTERVAL=1',
            ];
        });
    }

    /**
     * Configure the model factory to create a weekly recurring reset.
     */
    public function weekly(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
            ];
        });
    }

    /**
     * Configure the model factory to create a monthly recurring reset.
     */
    public function monthly(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'rrule' => 'FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=1',
            ];
        });
    }
}
