<?php

declare(strict_types=1);

namespace App\Http\Controllers\StageSafety;

use App\Http\Controllers\Controller;
use App\Http\Requests\StageSafety\SensorMonitoringIndexRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\MonitoringService;
use Illuminate\Http\JsonResponse;

class SensorMonitoringController extends Controller
{
    public function index(
        SensorMonitoringIndexRequest $request,
        Organization $organization,
        Sensor $stageSafetySensor,
        MonitoringService $monitoringService,
    ): JsonResponse {
        [$from, $to] = $request->range();

        return response()->json($monitoringService->sensorMonitoring($organization, $stageSafetySensor, $from, $to));
    }
}
