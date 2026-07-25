<?php

declare(strict_types=1);

namespace App\Http\Controllers\StageSafety;

use App\Enums\StageSafety\SensorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StageSafety\SensorCreateRequest;
use App\Http\Requests\StageSafety\SensorDestroyRequest;
use App\Http\Requests\StageSafety\SensorEditRequest;
use App\Http\Requests\StageSafety\SensorIndexRequest;
use App\Http\Requests\StageSafety\SensorShowRequest;
use App\Http\Requests\StageSafety\SensorStoreRequest;
use App\Http\Requests\StageSafety\SensorUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\SensorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SensorController extends Controller
{
    public function index(SensorIndexRequest $request, Organization $organization, SensorService $sensorService): Response
    {
        return Inertia::render('stage-safety/Sensors', [
            'sensors' => $sensorService->getSensors($organization, $request->showArchived()),
            'organization' => $organization,
            'showArchived' => $request->showArchived(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(SensorCreateRequest $request, Organization $organization): Response
    {
        return Inertia::render('stage-safety/NewSensor', [
            'organization' => $organization,
            'sensorTypes' => $this->sensorTypes(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(SensorStoreRequest $request, Organization $organization, SensorService $sensorService): JsonResponse
    {
        $result = $sensorService->createWithToken($organization, $request->validated());

        return response()->json($result, 201)->header('Cache-Control', 'no-store, private');
    }

    public function show(SensorShowRequest $request, Organization $organization, Sensor $stageSafetySensor): Response
    {
        return Inertia::render('stage-safety/Sensor', [
            'organization' => $organization,
            'sensor' => $stageSafetySensor,
        ]);
    }

    public function edit(SensorEditRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): Response
    {
        $sensorService->verifySensorBelongsToOrganization($organization, $stageSafetySensor);

        return Inertia::render('stage-safety/EditSensor', [
            'organization' => $organization,
            'sensor' => $stageSafetySensor->loadExists(['tokens as has_active_token']),
            'sensorTypes' => $this->sensorTypes(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(SensorUpdateRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): RedirectResponse
    {
        $sensorService->update($organization, $stageSafetySensor, $request->validated());

        return to_route('stage-safety.sensors.index', [
            'organization' => $organization,
        ])->with('status', 'Sensor updated successfully.');
    }

    public function destroy(SensorDestroyRequest $request, Organization $organization, Sensor $stageSafetySensor, SensorService $sensorService): RedirectResponse
    {
        $sensorService->delete($organization, $stageSafetySensor);

        return to_route('stage-safety.sensors.index', [
            'organization' => $organization,
        ])->with('status', 'Sensor deleted successfully.');
    }

    /**
     * @return list<array{manufacturer: string, model: string, label: string}>
     */
    protected function sensorTypes(): array
    {
        return array_map(fn (SensorType $sensorType): array => [
            'manufacturer' => $sensorType->manufacturer(),
            'model' => $sensorType->model(),
            'label' => $sensorType->displayName(),
        ], SensorType::cases());
    }
}
