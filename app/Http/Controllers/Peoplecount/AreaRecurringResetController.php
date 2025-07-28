<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\DestroyAreaRecurringResetRequest;
use App\Http\Requests\Peoplecount\IndexAreaRecurringResetRequest;
use App\Http\Requests\Peoplecount\ShowAreaRecurringResetRequest;
use App\Http\Requests\Peoplecount\StoreAreaRecurringResetRequest;
use App\Http\Requests\Peoplecount\UpdateAreaRecurringResetRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Services\Peoplecount\AreaResetService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaRecurringResetController extends Controller
{
    public function __construct(private readonly AreaResetService $areaResetService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexAreaRecurringResetRequest $request, Organization $organization, Area $area): Response
    {
        return Inertia::render('peoplecount/AreaRecurringResets', [
            'area' => $area,
            'resets' => $this->areaResetService->getAreaRecurringResets($area),
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAreaRecurringResetRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $this->areaResetService->createRecurringReset($area, [
            'event_id' => $request->input('event_id'),
            'reset_value' => $request->input('reset_value'),
            'rrule' => $request->input('rrule'),
            'timezone' => $request->input('timezone'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('peoplecount.areas.recurring-resets.index', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Recurring reset schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowAreaRecurringResetRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): RedirectResponse
    {
        // forward to the edit page
        return redirect()->route('peoplecount.areas.recurring-resets.edit', [
            'organization' => $organization,
            'area' => $area,
            'recurring_reset' => $recurringReset,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAreaRecurringResetRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): RedirectResponse
    {
        $this->areaResetService->updateRecurringReset($recurringReset, [
            'event_id' => $request->input('event_id'),
            'reset_value' => $request->input('reset_value'),
            'rrule' => $request->input('rrule'),
            'timezone' => $request->input('timezone'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('peoplecount.areas.recurring-resets.show', [
            'organization' => $organization,
            'area' => $area,
            'recurring_reset' => $recurringReset,
        ])
            ->with('status', 'Recurring reset schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DestroyAreaRecurringResetRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): RedirectResponse
    {
        $this->areaResetService->deleteRecurringReset($recurringReset);

        return redirect()->route('peoplecount.areas.recurring-resets.index', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Recurring reset schedule deleted successfully.');
    }
}
