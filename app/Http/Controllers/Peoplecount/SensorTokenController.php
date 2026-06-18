<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\SensorTokenUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Http\RedirectResponse;

class SensorTokenController extends Controller
{
    public function __construct(private readonly SensorService $sensorService) {}

    /**
     * Update (regenerate) the API token for a sensor.
     *
     * @param  SensorTokenUpdateRequest  $request  Needed for Authorization
     */
    public function update(SensorTokenUpdateRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        $this->sensorService->verifySensorManagedByCurrentOrganization($sensor);

        $token = $this->sensorService->createOrRegenerateToken($sensor);
        $sensor->api_token = $token;
        $sensor->save();

        return to_route('peoplecount.sensors.index', [
            'organization' => $organization,
        ])->with('status', 'Sensor token regenerated successfully for '.$sensor->vendor.' '.$sensor->model.' '.$sensor->serial.'.');
    }
}
