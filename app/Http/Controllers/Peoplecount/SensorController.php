<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\SensorCreateRequest;
use App\Http\Requests\Peoplecount\SensorDestroyRequest;
use App\Http\Requests\Peoplecount\SensorEditRequest;
use App\Http\Requests\Peoplecount\SensorIndexRequest;
use App\Http\Requests\Peoplecount\SensorShowRequest;
use App\Http\Requests\Peoplecount\SensorStoreRequest;
use App\Http\Requests\Peoplecount\SensorUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\SensorService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SensorController extends Controller
{
    public function __construct(private readonly SensorService $sensorService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(SensorIndexRequest $request, Organization $organization): Response
    {
        $showArchived = $request->showArchived();

        return Inertia::render('peoplecount/Sensors', [
            'sensors' => $this->sensorService->getSensors($showArchived),
            'organization' => $organization,
            'showArchived' => $showArchived,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SensorStoreRequest $request, Organization $organization): RedirectResponse
    {
        $sensor = $this->sensorService->createWithToken(
            array_merge($request->validated(), ['organization_id' => $organization->id])
        );

        $displayName = $sensor->name ?? ($sensor->vendor.' '.$sensor->model.' '.$sensor->serial);

        return to_route('peoplecount.sensors.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Sensor created successfully ('.$displayName.').');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(SensorCreateRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/NewSensor', [
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  SensorShowRequest  $request  Required for Authorization
     */
    public function show(SensorShowRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        return to_route('peoplecount.sensors.edit', [
            'organization' => $organization,
            'sensor' => $sensor,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SensorEditRequest $request, Organization $organization, Sensor $sensor): Response
    {
        $this->sensorService->verifySensorManagedByCurrentOrganization($sensor);

        // get the last 10 interval counts for the sensor
        $sensor->load(['intervalCounts' => function (HasMany $query) {
            $query->orderBy('ts_from', 'desc')->take(10);
        }, 'shares' => function (HasMany $query) {
            $query->with('borrowerOrganization')
                ->withCount('assignments')
                ->latest('starts_at');
        }]);

        return Inertia::render('peoplecount/EditSensor', [
            'organization' => $organization,
            'sensor' => $sensor,
            'organizations' => Organization::query()
                ->whereKeyNot($organization->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  SensorUpdateRequest  $request  Required for Authorization
     */
    public function update(SensorUpdateRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        $this->sensorService->verifySensorManagedByCurrentOrganization($sensor);

        $sensor->update($request->validated());

        $displayName = $sensor->name ?? ($sensor->vendor.' '.$sensor->model.' '.$sensor->serial);

        return to_route('peoplecount.sensors.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Sensor updated successfully ('.$displayName.').');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  SensorDestroyRequest  $request  Required for Authorization
     */
    public function destroy(SensorDestroyRequest $request, Organization $organization, Sensor $sensor): RedirectResponse
    {
        $name = $sensor->name ?? ($sensor->vendor.' '.$sensor->model.' '.$sensor->serial);

        try {
            $this->sensorService->delete($sensor);
        } catch (ValidationException $validationException) {
            return back()->withErrors($validationException->errors());
        }

        return to_route('peoplecount.sensors.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Sensor '.$name.' deleted successfully.');
    }
}
