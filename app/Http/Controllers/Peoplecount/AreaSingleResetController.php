<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\DestroyAreaSingleResetRequest;
use App\Http\Requests\Peoplecount\IndexAreaSingleResetRequest;
use App\Http\Requests\Peoplecount\StoreAreaSingleResetRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaSingleReset;
use App\Services\Peoplecount\AreaResetService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaSingleResetController extends Controller
{
    public function __construct(private readonly AreaResetService $areaResetService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexAreaSingleResetRequest $request, Organization $organization, Area $area): Response
    {
        return Inertia::render('peoplecount/AreaSingleResets', [
            'area' => $area,
            'resets' => $this->areaResetService->getAreaResets($area),
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAreaSingleResetRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $this->areaResetService->createSingleReset($area, [
            'reset_value' => $request->input('reset_value'),
            'effective_at' => $request->input('effective_at'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('peoplecount.areas.single-resets.index', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Manual reset created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DestroyAreaSingleResetRequest $request, Organization $organization, Area $area, AreaSingleReset $singleReset): RedirectResponse
    {
        $this->areaResetService->deleteSingleReset($singleReset);

        return redirect()->route('peoplecount.areas.single-resets.index', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Manual reset deleted successfully.');
    }
}
