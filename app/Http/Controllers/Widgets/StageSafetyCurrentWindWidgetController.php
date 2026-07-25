<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\StageSafety\CurrentWindIndexRequest;
use App\Models\Organization;
use App\Services\StageSafety\MonitoringService;
use Illuminate\Http\JsonResponse;

class StageSafetyCurrentWindWidgetController extends Controller
{
    public function index(CurrentWindIndexRequest $request, Organization $organization, MonitoringService $monitoringService): JsonResponse
    {
        return response()->json($monitoringService->currentWind($organization));
    }
}
