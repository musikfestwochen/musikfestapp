<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\AreaRecurringResetCreateRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetDestroyRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetEditRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetShowRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetStoreRequest;
use App\Http\Requests\Peoplecount\AreaRecurringResetUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Area;
use App\Models\Peoplecount\AreaRecurringReset;
use App\Services\Peoplecount\AreaResetService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaRecurringResetController extends Controller
{
    public function __construct(private readonly AreaResetService $areaResetService) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(AreaRecurringResetCreateRequest $request, Organization $organization, Area $area): Response
    {
        return Inertia::render('peoplecount/NewRecurringReset', [
            'organization' => $organization,
            'area' => $area,
            'timezones' => collect(DateTimeZone::listIdentifiers())->map(fn (string $timezone): array => [
                'value' => $timezone,
                'label' => $timezone,
            ])->values(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AreaRecurringResetStoreRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $this->areaResetService->createRecurringReset($area, [
            'reset_value' => $request->input('reset_value'),
            'reset_time' => $request->input('reset_time'),
            'timezone' => $request->input('timezone'),
            'notes' => $request->input('notes'),
        ]);

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Recurring reset schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AreaRecurringResetShowRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): RedirectResponse
    {
        // forward to the edit page
        return to_route('peoplecount.areas.recurring-resets.edit', [
            'organization' => $organization,
            'area' => $area,
            'recurring_reset' => $recurringReset,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AreaRecurringResetEditRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): Response
    {
        return Inertia::render('peoplecount/EditRecurringReset', [
            'organization' => $organization,
            'area' => $area,
            'recurringReset' => $recurringReset,
            'timezones' => collect(DateTimeZone::listIdentifiers())->map(fn (string $timezone): array => [
                'value' => $timezone,
                'label' => $timezone,
            ])->values(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AreaRecurringResetUpdateRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): RedirectResponse
    {
        $this->areaResetService->updateRecurringReset($recurringReset, [
            'reset_value' => $request->input('reset_value'),
            'reset_time' => $request->input('reset_time'),
            'timezone' => $request->input('timezone'),
            'notes' => $request->input('notes'),
        ]);

        return to_route('peoplecount.areas.recurring-resets.show', [
            'organization' => $organization,
            'area' => $area,
            'recurring_reset' => $recurringReset,
        ])
            ->with('status', 'Recurring reset schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AreaRecurringResetDestroyRequest $request, Organization $organization, Area $area, AreaRecurringReset $recurringReset): RedirectResponse
    {
        $this->areaResetService->deleteRecurringReset($recurringReset);

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])
            ->with('status', 'Recurring reset schedule deleted successfully.');
    }
}
