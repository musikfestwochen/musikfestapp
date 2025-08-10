<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\AreaCreateRequest;
use App\Http\Requests\Peoplecount\AreaDestroyRequest;
use App\Http\Requests\Peoplecount\AreaEditRequest;
use App\Http\Requests\Peoplecount\AreaIndexRequest;
use App\Http\Requests\Peoplecount\AreaShowRequest;
use App\Http\Requests\Peoplecount\AreaStoreRequest;
use App\Http\Requests\Peoplecount\AreaUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\Event;
use App\Services\Peoplecount\AreaService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function __construct(private readonly AreaService $areaService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(AreaIndexRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/Areas', [
            'areas' => $this->areaService->getAreas(),
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AreaStoreRequest $request, Organization $organization): RedirectResponse
    {
        $area = $this->areaService->create([
            'name' => $request->input('name'),
            'event_id' => $request->input('event_id'),
            'occupancy_alert_threshold' => $request->input('occupancy_alert_threshold'),
        ]);

        return redirect()->route('peoplecount.areas.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Area created successfully ('.$area->name.').');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(AreaCreateRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/NewArea', [
            'organization' => $organization,
            'events' => Event::query()->where('organization_id', $organization->id)->get(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  AreaShowRequest  $request  Required for Authorization
     */
    public function show(AreaShowRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        return redirect()->route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AreaEditRequest $request, Organization $organization, Area $area): Response
    {
        return Inertia::render('peoplecount/EditArea', [
            'organization' => $organization,
            'area' => $this->areaService->getWithRelations($area),
            'events' => Event::query()->where('organization_id', $organization->id)->get(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  AreaUpdateRequest  $request  Required for Authorization
     */
    public function update(AreaUpdateRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $area = $this->areaService->update($area, [
            'name' => $request->input('name'),
            'event_id' => $request->input('event_id'),
            'occupancy_alert_threshold' => $request->input('occupancy_alert_threshold'),
        ]);

        return redirect()->route('peoplecount.areas.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Area '.$area->name.' updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  AreaDestroyRequest  $request  Required for Authorization
     */
    public function destroy(AreaDestroyRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $name = $area->name;
        $area->delete();

        return redirect()->route('peoplecount.areas.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Area '.$name.' deleted successfully.');
    }
}
