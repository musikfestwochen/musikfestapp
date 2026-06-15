<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\Peoplecount\AreaCountHistoryIndexRequest;
use App\Models\Organization;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

class PeoplecountAreaCountHistoryWidgetController extends Controller
{
    public function __construct(
        private readonly AreaAggregationService $areaAggregationService
    ) {}

    public function index(AreaCountHistoryIndexRequest $request, Organization $organization): JsonResponse
    {
        $timezone = (string) config('app.timezone');
        $now = Date::now()->setTimezone($timezone);
        $from = $request->validated('from')
            ? Date::parse($request->validated('from'))->setTimezone($timezone)
            : $now->copy()->subHour();
        $to = $request->validated('to')
            ? Date::parse($request->validated('to'))->setTimezone($timezone)
            : $now;

        $series = $this->areaAggregationService->getActiveAreaAggregatedCountsHistory($organization, $from, $to);

        return response()->json($series);
    }
}
