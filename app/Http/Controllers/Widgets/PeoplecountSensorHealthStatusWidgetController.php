<?php

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\Peoplecount\SensorHealthIndexRequest;
use App\Models\Organization;
use App\Services\Peoplecount\SensorService;
use Illuminate\Http\JsonResponse;

class PeoplecountSensorHealthStatusWidgetController extends Controller
{
    public function __construct(
        private readonly SensorService $sensorService
    ) {}

    /**
     * Get health status for currently assigned sensors in the organization.
     */
    public function index(SensorHealthIndexRequest $request, Organization $organization): JsonResponse
    {
        $payload = $this->sensorService->getAssignedSensorsHealthStatus($organization);

        return response()->json($payload);
    }
}
