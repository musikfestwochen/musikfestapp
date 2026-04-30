<?php

namespace Database\Factories\Peoplecount;

use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<AreaAggregatedCount>
 */
class AreaAggregatedCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-1 day', 'now');
        $periodEnd = Date::instance($periodStart)->addMinutes(10);

        return [
            'area_id' => Area::query()->inRandomOrder()->first()->id ?? Area::factory()->create()->id,
            'count' => fake()->numberBetween(0, 1000),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'checksum' => bin2hex(random_bytes(16)), // Generate a random hex string for checksum
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
     * Configure the model factory to use specific period times.
     */
    public function withPeriod(Carbon $start, Carbon $end): static
    {
        return $this->state(function (array $attributes) use ($start, $end): array {
            return [
                'period_start' => $start,
                'period_end' => $end,
            ];
        });
    }
}
