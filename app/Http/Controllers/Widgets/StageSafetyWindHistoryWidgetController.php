<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\StageSafety\WindHistoryIndexRequest;
use App\Models\Organization;
use App\Services\StageSafety\MonitoringService;
use Illuminate\Http\JsonResponse;

class StageSafetyWindHistoryWidgetController extends Controller
{
    public function index(WindHistoryIndexRequest $request, Organization $organization, MonitoringService $monitoringService): JsonResponse
    {
        [$from, $to] = $request->range();

        return response()->json($monitoringService->windHistory($organization, $from, $to));
    }
}
