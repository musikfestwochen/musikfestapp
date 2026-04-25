<?php

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\Peoplecount\AreaCountHistoryIndexRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaAggregatedCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PeoplecountAreaCountHistoryWidgetController extends Controller
{
    public function index(AreaCountHistoryIndexRequest $request, Organization $organization): JsonResponse
    {
        $now = Carbon::now()->setTimezone('UTC');
        $from = $request->validated('from')
            ? Carbon::parse($request->validated('from'))->setTimezone('UTC')
            : $now->copy()->subHour();
        $to = $request->validated('to')
            ? Carbon::parse($request->validated('to'))->setTimezone('UTC')
            : $now;

        $areas = Area::query()
            ->whereHas('event', function (Builder $query) use ($organization, $now) {
                $query->where('organization_id', $organization->id)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now);
            })
            ->with([
                'event:id,name',
                'aggregatedCounts' => function ($query) use ($from, $to) {
                    $query->where('period_start', '>=', $from)
                        ->where('period_end', '<=', $to)
                        ->orderBy('period_start', 'asc')
                        ->select(['id', 'area_id', 'count', 'period_start', 'period_end']);
                },
            ])
            ->get(['id', 'name', 'event_id']);

        $series = $areas->map(function (Area $area) {
            return [
                'id' => $area->id,
                'name' => $area->name,
                'event_name' => $area->event->name,
                'data' => $area->aggregatedCounts->map(function (AreaAggregatedCount $count) {
                    return [
                        'time' => $count->period_end->toIso8601String(),
                        'count' => $count->count,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($series);
    }
}
