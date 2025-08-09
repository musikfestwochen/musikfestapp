<?php

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\Peoplecount\MostActiveSensorsIndexRequest;
use App\Models\Organization;
use App\Services\Peoplecount\SensorActivityService;
use Illuminate\Http\JsonResponse;

class PeoplecountMostActiveSensorsWidgetController extends Controller
{
    public function __construct(
        private readonly SensorActivityService $sensorActivityService
    ) {}

    /**
     * Get most active sensors per area for the given organization.
     */
    public function index(MostActiveSensorsIndexRequest $request, Organization $organization): JsonResponse
    {
        $payload = $this->sensorActivityService->getMostActiveSensorsPerArea($organization);

        return response()->json($payload);
    }
}
