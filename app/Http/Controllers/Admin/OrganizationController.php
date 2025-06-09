<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrganizationCreateRequest;
use App\Http\Requests\Admin\OrganizationDestroyRequest;
use App\Http\Requests\Admin\OrganizationEditRequest;
use App\Http\Requests\Admin\OrganizationIndexRequest;
use App\Http\Requests\Admin\OrganizationShowRequest;
use App\Http\Requests\Admin\OrganizationStoreRequest;
use App\Http\Requests\Admin\OrganizationUpdateRequest;
use App\Models\Organization;
use Exception;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OrganizationIndexRequest $request): Response
    {
        $organizations = Organization::query();

        $sort = $request->input('sort', 'name');
        $direction = $request->input('order', 'asc');

        if (in_array($sort, ['name', 'email', 'website', 'created_at'])) {
            $organizations->orderBy($sort, $direction);
        }

        return Inertia::render('admin/Organizations', [
            'organizations' => $organizations->paginate(10)->withQueryString(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OrganizationStoreRequest $request): RedirectResponse
    {
        $organization = Organization::query()->create($request->all());

        return redirect()->route('organizations.index')->with('status', 'Organization '.$organization->name.' created successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(OrganizationCreateRequest $request): Response
    {
        return Inertia::render('admin/NewOrganizationPage', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  OrganizationShowRequest  $request  Required for authorization, even if not explicitly used in method body
     *
     * @throws Exception
     */
    public function show(OrganizationShowRequest $request, Organization $organization): RedirectResponse
    {
        return redirect()->route('organizations.edit', $organization);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  OrganizationEditRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function edit(OrganizationEditRequest $request, Organization $organization): Response
    {
        return Inertia::render('admin/EditOrganizationPage', [
            'organization' => $organization,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OrganizationUpdateRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->all());

        return redirect()->route('organizations.index')->with('status', 'Organization '.$organization->name.' updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  OrganizationDestroyRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function destroy(OrganizationDestroyRequest $request, Organization $organization): RedirectResponse
    {
        // save organization name for redirect message
        $name = $organization->name;

        // delete organization
        $organization->delete();

        return redirect()->route('organizations.index')->with('status', 'Organization '.$name.' deleted successfully.');
    }
}
