<?php

declare(strict_types=1);

namespace App\Http\Controllers\StageSafety;

use App\Http\Controllers\Controller;
use App\Http\Requests\StageSafety\SensorTokenUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\SensorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SensorTokenController extends Controller
{
    public function update(SensorTokenUpdateRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): JsonResponse
    {
        $token = $sensorService->createOrRegenerateToken($organization, $stageSafetySensor);

        return response()->json(['token' => $token])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(SensorTokenUpdateRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): RedirectResponse
    {
        $sensorService->revokeTokens($organization, $stageSafetySensor);

        return to_route('stage-safety.sensors.edit', [
            'organization' => $organization,
            'stageSafetySensor' => $stageSafetySensor,
        ])->with('status', 'Sensor token revoked successfully.');
    }
}
