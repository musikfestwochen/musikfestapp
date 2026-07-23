<?php

declare(strict_types=1);

namespace App\Http\Controllers\StageSafety;

use App\Http\Controllers\Controller;
use App\Http\Requests\StageSafety\SensorArchiveUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\SensorService;
use Illuminate\Http\RedirectResponse;

class SensorArchiveController extends Controller
{
    public function store(SensorArchiveUpdateRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): RedirectResponse
    {
        $sensorService->archive($organization, $stageSafetySensor);

        return to_route('stage-safety.sensors.index', [
            'organization' => $organization,
        ])->with('status', 'Sensor archived successfully.');
    }

    public function destroy(SensorArchiveUpdateRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): RedirectResponse
    {
        $sensorService->restore($organization, $stageSafetySensor);

        return to_route('stage-safety.sensors.index', [
            'organization' => $organization,
            'archived' => true,
        ])->with('status', 'Sensor restored successfully.');
    }
}
