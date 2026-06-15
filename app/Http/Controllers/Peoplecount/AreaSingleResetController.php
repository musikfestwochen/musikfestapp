<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\AreaSingleResetCreateRequest;
use App\Http\Requests\Peoplecount\AreaSingleResetDestroyRequest;
use App\Http\Requests\Peoplecount\AreaSingleResetStoreRequest;
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
     * Show the form for creating a new resource.
     */
    public function create(AreaSingleResetCreateRequest $request, Organization $organization, Area $area): Response
    {
        return Inertia::render('peoplecount/NewSingleReset', [
            'area' => $area,
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AreaSingleResetStoreRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $this->areaResetService->createSingleReset($area, [
            'reset_value' => $request->input('reset_value'),
            'effective_at' => $request->input('effective_at'),
            'notes' => $request->input('notes'),
        ]);

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Manual reset created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AreaSingleResetDestroyRequest $request, Organization $organization, Area $area, AreaSingleReset $singleReset): RedirectResponse
    {
        $this->areaResetService->deleteSingleReset($singleReset);

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Manual reset deleted successfully.');
    }
}
