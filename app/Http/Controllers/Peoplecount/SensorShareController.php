<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\SensorShareDestroyRequest;
use App\Http\Requests\Peoplecount\SensorShareStoreRequest;
use App\Http\Requests\Peoplecount\SensorShareUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Models\Peoplecount\SensorShare;
use App\Services\Peoplecount\SensorShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SensorShareController extends Controller
{
    public function __construct(private readonly SensorShareService $sensorShareService) {}

    public function store(SensorShareStoreRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        try {
            $this->sensorShareService->create([
                'sensor_id' => $sensor->id,
                ...$request->payload(),
            ]);
        } catch (ValidationException $validationException) {
            return back()->withErrors($validationException->errors())->withInput();
        }

        return to_route('peoplecount.sensors.edit', [
            'organization' => $organization,
            'sensor' => $sensor,
        ])->with('status', 'Sensor shared successfully.');
    }

    public function update(SensorShareUpdateRequest $request, Organization $organization, Sensor $sensor, SensorShare $share): RedirectResponse
    {
        abort_if($share->sensor_id !== $sensor->id, 404);

        try {
            $this->sensorShareService->update($share, $request->payload());
        } catch (ValidationException $validationException) {
            return back()->withErrors($validationException->errors())->withInput();
        }

        return to_route('peoplecount.sensors.edit', [
            'organization' => $organization,
            'sensor' => $sensor,
        ])->with('status', 'Sensor share updated successfully.');
    }

    public function destroy(SensorShareDestroyRequest $request, Organization $organization, Sensor $sensor, SensorShare $share): RedirectResponse
    {
        abort_if($share->sensor_id !== $sensor->id, 404);

        try {
            $this->sensorShareService->delete($share);
        } catch (ValidationException $validationException) {
            return back()->withErrors($validationException->errors());
        }

        return to_route('peoplecount.sensors.edit', [
            'organization' => $organization,
            'sensor' => $sensor,
        ])->with('status', 'Sensor share deleted successfully.');
    }
}
