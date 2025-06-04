<?php

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
        $users = User::query();

        $sort = $request->input('sort', 'name');
        $direction = $request->input('order', 'asc');

        if (in_array($sort, ['name', 'email', 'created_at'])) {
            $users->orderBy($sort, $direction);
        }

        return Inertia::render('admin/Users', [
            'users' => $users->paginate(10)->withQueryString(),
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

        $user = User::create($request->all());

        return redirect()->route('users.index')->with('status', 'User '.$user->name.' created successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(UserCreateRequest $request): Response
    {
        return Inertia::render('admin/NewUserPage', [
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
        return redirect()->route('users.edit', $user);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  UserEditRequest  $request  Required for authorization, even if not explicitly used in method body
     */
    public function edit(UserEditRequest $request, User $user): Response
    {
        return Inertia::render('admin/EditUserPage', [
            'user' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update($request->all());

        return redirect()->route('users.index')->with('status', 'User '.$user->name.' updated successfully.');
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

        return redirect()->route('users.index')->with('status', 'User '.$name.' deleted successfully.');
    }
}
