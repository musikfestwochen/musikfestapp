<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\Peoplecount\ActiveAreaCountsIndexRequest;
use App\Models\Organization;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Http\JsonResponse;

class PeoplecountActiveAreaCountsWidgetController extends Controller
{
    public function __construct(
        private readonly AreaAggregationService $areaAggregationService
    ) {}

    /**
     * Get the latest aggregated counts for active areas.
     */
    public function index(ActiveAreaCountsIndexRequest $request, Organization $organization): JsonResponse
    {
        $activeAreaCounts = $this->areaAggregationService->getActiveAreaAggregatedCounts($organization);

        return response()->json($activeAreaCounts);
    }
}
