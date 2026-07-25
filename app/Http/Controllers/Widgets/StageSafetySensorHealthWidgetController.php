<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\StageSafety\SensorHealthIndexRequest;
use App\Models\Organization;
use App\Services\StageSafety\MonitoringService;
use Illuminate\Http\JsonResponse;

class StageSafetySensorHealthWidgetController extends Controller
{
    public function index(SensorHealthIndexRequest $request, Organization $organization, MonitoringService $monitoringService): JsonResponse
    {
        return response()->json($monitoringService->sensorHealth($organization));
    }
}
