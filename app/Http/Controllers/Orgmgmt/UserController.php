<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orgmgmt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orgmgmt\UserCreateRequest;
use App\Http\Requests\Orgmgmt\UserDestroyRequest;
use App\Http\Requests\Orgmgmt\UserEditRequest;
use App\Http\Requests\Orgmgmt\UserIndexRequest;
use App\Http\Requests\Orgmgmt\UserShowRequest;
use App\Http\Requests\Orgmgmt\UserStoreRequest;
use App\Http\Requests\Orgmgmt\UserUpdateRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserIndexRequest $request, UserService $userService, Organization $organization): Response
    {
        return Inertia::render('orgmgmt/Users', [
            'organization' => $organization,
            'users' => $userService->getUsers(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserStoreRequest $request, UserService $userService, Organization $organization): RedirectResponse
    {
        $user = $userService->createOrAttachToOrganization($organization, $request->payload());

        return to_route('orgmgmt.users.index', [
            'organization' => $organization,
        ])
            ->with('status', 'User '.$user->name.' added successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(UserCreateRequest $request, Organization $organization): Response
    {
        return Inertia::render('orgmgmt/NewUser', [
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  UserShowRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function show(UserShowRequest $request, Organization $organization, User $user): RedirectResponse
    {
        return to_route('orgmgmt.users.edit', [
            'organization' => $organization,
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  UserEditRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function edit(UserEditRequest $request, Organization $organization, User $user): Response
    {
        return Inertia::render('orgmgmt/EditUser', [
            'organization' => $organization,
            'user' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, Organization $organization, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return to_route('orgmgmt.users.index', [
            'organization' => $organization,
        ])->with('status', 'User '.$user->name.' updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  UserDestroyRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function destroy(UserDestroyRequest $request, UserService $userService, Organization $organization, User $user): RedirectResponse
    {
        $name = $user->name;

        $userService->removeFromOrganization($user, $organization);

        return to_route('orgmgmt.users.index', [
            'organization' => $organization,
        ])->with('status', 'User '.$name.' removed successfully.');
    }
}
