<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'sort' => 'in:name,email,website',
            'order' => 'in:asc,desc',
        ]);

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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:organizations',
            'slug' => 'required|string|max:255|unique:organizations',
            'description' => 'nullable|string',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
        ]);

        $organization = Organization::create($request->all());

        return redirect()->route('organizations.index')->with('status', 'Organization '.$organization->name.' created successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('admin/NewOrganizationPage', [
            'status' => request()->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @throws Exception
     */
    public function show(Organization $organization): RedirectResponse
    {
        return redirect()->route('organizations.edit', $organization);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Organization $organization): Response
    {
        return Inertia::render('admin/EditOrganizationPage', [
            'organization' => $organization,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name,'.$organization->id,
            'slug' => 'required|string|max:255|unique:organizations,slug,'.$organization->id,
            'description' => 'nullable|string',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
        ]);

        $organization->update($request->all());

        return redirect()->route('organizations.index')->with('status', 'Organization '.$organization->name.' updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization): RedirectResponse
    {
        // save organization name for redirect message
        $name = $organization->name;

        // delete organization
        $organization->delete();

        return redirect()->route('organizations.index')->with('status', 'Organization '.$name.' deleted successfully.');
    }
}
