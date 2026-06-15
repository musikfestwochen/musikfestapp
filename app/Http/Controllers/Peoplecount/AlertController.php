<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\AlertCreateRequest;
use App\Http\Requests\Peoplecount\AlertDestroyRequest;
use App\Http\Requests\Peoplecount\AlertEditRequest;
use App\Http\Requests\Peoplecount\AlertShowRequest;
use App\Http\Requests\Peoplecount\AlertStoreRequest;
use App\Http\Requests\Peoplecount\AlertUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Alert;
use App\Models\Peoplecount\Area;
use App\Services\Peoplecount\AlertService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly UserService $userService,
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(AlertCreateRequest $request, Organization $organization, Area $area): Response
    {
        $users = $this->userService->getUsers($organization, ['users.id', 'users.name', 'users.email']);

        return Inertia::render('peoplecount/NewAlert', [
            'organization' => $organization,
            'area' => $area,
            'users' => $users,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AlertStoreRequest $request, Organization $organization, Area $area): RedirectResponse
    {
        $this->alertService->storeAreaAlert($organization, $area, $request->validated());

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])->with('status', 'Alert created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  AlertShowRequest  $request  Required for Authorization
     */
    public function show(AlertShowRequest $request, Organization $organization, Area $area, Alert $alert): RedirectResponse
    {
        return to_route('peoplecount.areas.alerts.edit', [
            'organization' => $organization,
            'area' => $area,
            'alert' => $alert,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AlertEditRequest $request, Organization $organization, Area $area, Alert $alert): Response
    {
        $alert->load('recipients');
        $users = $this->userService->getUsers($organization, ['users.id', 'users.name', 'users.email']);

        return Inertia::render('peoplecount/EditAlert', [
            'organization' => $organization,
            'area' => $area,
            'alert' => $alert,
            'users' => $users,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  AlertUpdateRequest  $request  Required for Authorization
     */
    public function update(AlertUpdateRequest $request, Organization $organization, Area $area, Alert $alert): RedirectResponse
    {
        $this->alertService->updateAreaAlert($organization, $area, $alert, $request->validated());

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])->with('status', 'Alert updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  AlertDestroyRequest  $request  Required for Authorization
     */
    public function destroy(AlertDestroyRequest $request, Organization $organization, Area $area, Alert $alert): RedirectResponse
    {
        $this->alertService->destroyAreaAlert($organization, $area, $alert);

        return to_route('peoplecount.areas.edit', [
            'organization' => $organization,
            'area' => $area,
        ])->with('status', 'Alert deleted successfully.');
    }
}
