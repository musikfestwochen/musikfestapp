<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationSelectionRequest;
use App\Services\OrganizationSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSelectionController extends Controller
{
    protected OrganizationSelectionService $organizationSelectionService;

    /**
     * Create a new controller instance.
     */
    public function __construct(OrganizationSelectionService $organizationSelectionService)
    {
        $this->organizationSelectionService = $organizationSelectionService;
    }

    /**
     * Show the organization selection page.
     */
    public function index(): Response|RedirectResponse
    {
        $organizations = $this->organizationSelectionService->getOrganizationsForUser();

        // If user has only one organization, automatically select it
        if ($organizations->count() === 1) {
            return to_route('organization.dashboard', ['organization' => $organizations->first()->slug]);
        }

        return Inertia::render('OrganizationSelection', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Store the organization selection.
     */
    public function store(OrganizationSelectionRequest $request): RedirectResponse
    {
        try {
            $organizationSlug = $this->organizationSelectionService->processOrganizationSelection($request->organization_id);

            return to_route('organization.dashboard', ['organization' => $organizationSlug]);
        } catch (AuthorizationException $authorizationException) {

            return to_route('organization-selection.index')->with('error', $authorizationException->getMessage());
        }
    }
}
