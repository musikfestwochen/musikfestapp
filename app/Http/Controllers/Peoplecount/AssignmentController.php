<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\AssignmentCreateRequest;
use App\Http\Requests\Peoplecount\AssignmentDestroyRequest;
use App\Http\Requests\Peoplecount\AssignmentEditRequest;
use App\Http\Requests\Peoplecount\AssignmentIndexRequest;
use App\Http\Requests\Peoplecount\AssignmentShowRequest;
use App\Http\Requests\Peoplecount\AssignmentStoreRequest;
use App\Http\Requests\Peoplecount\AssignmentUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Assignment;
use App\Services\Peoplecount\AssignmentService;
use App\Services\Peoplecount\SensorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignmentService,
        private readonly SensorService $sensorService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(AssignmentIndexRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/Assignments', [
            'assignments' => $this->assignmentService->getAssignments(),
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AssignmentStoreRequest $request, Organization $organization): RedirectResponse
    {
        try {
            $this->assignmentService->create([
                'event_id' => $request->validated('event_id'),
                'area_id' => $request->validated('area_id'),
                'sensor_id' => $request->validated('sensor_id'),
                'direction_flipped' => $request->validated('direction_flipped'),
                'active_from' => $request->validated('active_from'),
                'active_to' => $request->validated('active_to'),
            ]);

            return to_route('peoplecount.assignments.index', [
                'organization' => $organization,
            ])
                ->with('status', 'Assignment created successfully.');
        } catch (ValidationException $validationException) {
            return back()->withErrors($validationException->errors())->withInput();
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(AssignmentCreateRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/NewAssignment', [
            'organization' => $organization,
            'events' => $organization->events()->with('areas')->get(),
            'sensors' => $this->sensorService->getAssignableSensorsForOrganization($organization),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  AssignmentShowRequest  $request  Required for Authorization
     */
    public function show(AssignmentShowRequest $request, Organization $organization, Assignment $assignment): RedirectResponse
    {
        $this->assignmentService->verifyAssignmentBelongsToCurrentOrganization($assignment);

        return to_route('peoplecount.assignments.edit', [
            'organization' => $organization,
            'assignment' => $assignment,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AssignmentEditRequest $request, Organization $organization, Assignment $assignment): Response
    {
        $this->assignmentService->verifyAssignmentBelongsToCurrentOrganization($assignment);

        // Load relationships
        $assignment->load(['event', 'area', 'sensor.organization']);

        return Inertia::render('peoplecount/EditAssignment', [
            'organization' => $organization,
            'assignment' => $assignment,
            'events' => $organization->events()->with('areas')->get(),
            'sensors' => $this->sensorService->getAssignableSensorsForAssignmentEdit($organization, $assignment),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  AssignmentUpdateRequest  $request  Required for Authorization
     */
    public function update(AssignmentUpdateRequest $request, Organization $organization, Assignment $assignment): RedirectResponse
    {
        try {
            $this->assignmentService->update($assignment, [
                'event_id' => $request->validated('event_id'),
                'area_id' => $request->validated('area_id'),
                'sensor_id' => $request->validated('sensor_id'),
                'direction_flipped' => $request->validated('direction_flipped'),
                'active_from' => $request->validated('active_from'),
                'active_to' => $request->validated('active_to'),
            ]);

            return to_route('peoplecount.assignments.index', [
                'organization' => $organization,
            ])
                ->with('status', 'Assignment updated successfully.');
        } catch (ValidationException $validationException) {
            return back()->withErrors($validationException->errors())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  AssignmentDestroyRequest  $request  Required for Authorization
     */
    public function destroy(AssignmentDestroyRequest $request, Organization $organization, Assignment $assignment): RedirectResponse
    {
        $this->assignmentService->verifyAssignmentBelongsToCurrentOrganization($assignment);

        $assignment->delete();

        return to_route('peoplecount.assignments.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Assignment deleted successfully.');
    }
}
