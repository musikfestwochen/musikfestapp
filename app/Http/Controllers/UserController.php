<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
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
    public function store(Request $request): RedirectResponse
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
        ]);

        // create user a random password
        $request->merge(['password' => Str::random(8)]);

        $user = User::create($request->all());

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('admin/UserForm');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): Response
    {
        throw new Exception('Not implemented');
    }
}
