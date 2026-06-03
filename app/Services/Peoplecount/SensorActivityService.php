<?php

namespace App\Services\Peoplecount;

use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\IntervalCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

class SensorActivityService
{
    /**
     * Return per-area sensors with sums for the last 10m, 30m, 1h, 2h.
     * Sums respect active assignments and direction flipping (swap in/out when flipped).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMostActiveSensorsPerArea(Organization $organization): array
    {
        $cacheTtlSeconds = 5;
        $cacheKey = 'peoplecount:most_active_sensors:org:'.$organization->id;

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), function () use ($organization): array {
            $timezone = (string) config('app.timezone');
            $now = Date::now()->setTimezone($timezone);
            $windows = [
                '10m' => $now->copy()->subMinutes(10),
                '30m' => $now->copy()->subMinutes(30),
                '1h' => $now->copy()->subHour(),
                '2h' => $now->copy()->subHours(2),
            ];
            $earliestFrom = min( // earliest lower bound
                $windows['10m'],
                $windows['30m'],
                $windows['1h'],
                $windows['2h']
            );

            // Find active areas (events in progress) and their currently active assignments
            $areas = Area::query()
                ->whereHas('event', function (Builder $q) use ($organization, $now) {
                    $q->where('organization_id', $organization->id)
                        ->where('starts_at', '<=', $now)
                        ->where('ends_at', '>=', $now);
                })
                ->with([
                    'event:id,name',
                    'assignments' => function (Relation $q) use ($now) {
                        $q->where('active_from', '<=', $now)
                            ->where('active_to', '>=', $now)
                            ->with(['sensor:id,serial,vendor,model']);
                    },
                ])
                ->get(['id', 'name', 'event_id']);

            // Collect sensor IDs from active assignments
            $activeAssignments = $areas->flatMap(fn (Area $a) => $a->assignments);
            /** @var Collection<int, int> $sensorIds */
            $sensorIds = $activeAssignments->pluck('sensor_id')->unique()->values();

            // Fetch interval counts for all involved sensors within the widest window
            $countsBySensor = collect(); // sensor_id => Collection<IntervalCount>
            if ($sensorIds->isNotEmpty()) {
                $allCounts = IntervalCount::query()
                    ->whereIn('sensor_id', $sensorIds)
                    ->where('ts_from', '>=', $earliestFrom)
                    ->where('ts_from', '<', $now)
                    ->get(['sensor_id', 'ts_from', 'ts_to', 'count_in', 'count_out']);

                $countsBySensor = $allCounts->groupBy('sensor_id');
            }

            // Build payload
            $result = [];
            foreach ($areas as $area) {
                $sensors = [];
                foreach ($area->assignments as $assignment) {
                    $sensor = $assignment->sensor;
                    $sensorCounts = $countsBySensor->get($sensor->id, collect());

                    $sums = [];
                    foreach ($windows as $label => $from) {
                        $inSum = 0;
                        $outSum = 0;
                        foreach ($sensorCounts as $ic) {
                            // Only include when inside both the time window and assignment active period
                            if ($ic->ts_from >= $from && $ic->ts_from < $now && $ic->ts_from >= $assignment->active_from && $ic->ts_from < $assignment->active_to) {
                                $in = (int) $ic->count_in;
                                $out = (int) $ic->count_out;
                                if ($assignment->direction_flipped) {
                                    // swap in/out
                                    [$in, $out] = [$out, $in];
                                }

                                $inSum += $in;
                                $outSum += $out;
                            }
                        }

                        $sums[$label] = [
                            'in' => $inSum,
                            'out' => $outSum,
                            'total' => $inSum + $outSum,
                        ];
                    }

                    $sensors[] = [
                        'id' => $sensor->id,
                        'serial' => $sensor->serial,
                        'vendor' => $sensor->vendor,
                        'model' => $sensor->model,
                        'sums' => $sums,
                    ];
                }

                $result[] = [
                    'id' => $area->id,
                    'name' => $area->name,
                    'event_name' => $area->event->name,
                    'sensors' => $sensors,
                    'last_updated' => $now->toIso8601String(),
                ];
            }

            return $result;
        });
    }
}
