<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\SensorArchiveUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Http\RedirectResponse;

class SensorArchiveController extends Controller
{
    public function __construct(private readonly SensorService $sensorService) {}

    public function store(SensorArchiveUpdateRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        $this->sensorService->archive($sensor);

        return to_route('peoplecount.sensors.index', [
            'organization' => $organization,
        ])->with('status', 'Sensor archived successfully.');
    }

    public function destroy(SensorArchiveUpdateRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        $this->sensorService->unarchive($sensor);

        return to_route('peoplecount.sensors.index', [
            'organization' => $organization,
            'archived' => true,
        ])->with('status', 'Sensor restored successfully.');
    }
}
