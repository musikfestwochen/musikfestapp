<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserCreateRequest;
use App\Http\Requests\Admin\UserDestroyRequest;
use App\Http\Requests\Admin\UserEditRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserShowRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserIndexRequest $request): Response
    {
        $userService = resolve(UserService::class);

        return Inertia::render('admin/Users', [
            'users' => $userService->getUsers(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        // create user a random password
        $request->merge(['password' => Str::random()]);

        $user = User::query()->create($request->all());

        return to_route('admin.users.index')->with('status', 'User '.$user->name.' created successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(UserCreateRequest $request): Response
    {
        return Inertia::render('admin/NewUser', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  UserShowRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function show(UserShowRequest $request, User $user): RedirectResponse
    {
        return to_route('admin.users.edit', $user);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  UserEditRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function edit(UserEditRequest $request, User $user): Response
    {
        return Inertia::render('admin/EditUser', [
            'user' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update($request->all());

        return to_route('admin.users.index')->with('status', 'User '.$user->name.' updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  UserDestroyRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function destroy(UserDestroyRequest $request, User $user): RedirectResponse
    {
        // save user name for redirect message
        $name = $user->name;

        // delete user
        $user->delete();

        return to_route('admin.users.index')->with('status', 'User '.$name.' deleted successfully.');
    }
}
