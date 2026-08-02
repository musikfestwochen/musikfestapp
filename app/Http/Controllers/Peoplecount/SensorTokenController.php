<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\SensorTokenUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SensorTokenController extends Controller
{
    public function __construct(private readonly SensorService $sensorService) {}

    /**
     * Update (regenerate) the API token for a sensor.
     *
     * @param  SensorTokenUpdateRequest  $request  Needed for Authorization
     */
    public function update(SensorTokenUpdateRequest $request, Organization $organization, Sensor $sensor): JsonResponse
    {
        $token = $this->sensorService->createOrRegenerateToken($sensor);

        return response()->json(['token' => $token])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Revoke all API tokens for a sensor.
     *
     * @param  SensorTokenUpdateRequest  $request  Needed for Authorization
     */
    public function destroy(SensorTokenUpdateRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        $this->sensorService->revokeTokens($sensor);

        return to_route('peoplecount.sensors.edit', [
            'organization' => $organization,
            'sensor' => $sensor,
        ])->with('status', 'Sensor token revoked successfully.');
    }
}
