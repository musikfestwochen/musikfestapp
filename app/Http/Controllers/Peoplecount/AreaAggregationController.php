<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\AreaAggregationIndexRequest;
use App\Models\Organization;
use App\Services\Peoplecount\AreaAggregationService;
use Illuminate\Http\JsonResponse;

class AreaAggregationController extends Controller
{
    public function __construct(
        private readonly AreaAggregationService $areaAggregationService
    ) {}

    /**
     * Get the latest aggregated counts for active areas.
     */
    public function index(AreaAggregationIndexRequest $request, Organization $organization): JsonResponse
    {
        $activeAreaCounts = $this->areaAggregationService->getActiveAreaAggregatedCounts($organization);

        return response()->json($activeAreaCounts);
    }
}
