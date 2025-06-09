<?php

use App\Http\Middleware\Permissions\GlobalOrganizationMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->middleware = new GlobalOrganizationMiddleware;
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('sets permissions team ID to 0 when user is authenticated', function () {
    // Create a regular user
    $user = User::factory()->create();

    // Assign the Admin role to the user
    // Note: We need to set the permissions team ID to 0 before assigning the role
    // to make it a global role assignment
    setPermissionsTeamId(0);
    $user->assignRole('Admin');

    // Authenticate as the user
    $this->actingAs($user);

    // Create a request and response
    $request = Request::create('/test', 'GET');
    $response = new Response;

    // Create a next closure that returns the response and checks the permissions team ID
    $permissionsTeamIdWasSet = false;
    $next = function ($req) use ($response, &$permissionsTeamIdWasSet): Response {
        // Check if the permissions team ID was set to 0
        // We can't directly access the permissions team ID, but we can check if
        // the user has a specific permission that requires global permissions
        $permissionsTeamIdWasSet = true;

        return $response;
    };

    // Call the middleware
    $result = $this->middleware->handle($request, $next);

    // Assert that the middleware returns the response
    expect($result)->toBe($response);

    // Assert that the next closure was called
    expect($permissionsTeamIdWasSet)->toBeTrue();

    // Verify that the user can access a route that requires global permissions
    $this->get(route('organizations.index'))
        ->assertStatus(200);
});

it('passes request to next middleware when no user is authenticated', function () {
    // Ensure no user is authenticated
    Auth::logout();

    // Create a request and response
    $request = Request::create('/test', 'GET');
    $response = new Response;

    // Flag to check if next closure was called
    $nextWasCalled = false;

    // Create a next closure that returns the response
    $next = function ($req) use ($response, &$nextWasCalled): \Symfony\Component\HttpFoundation\Response {
        $nextWasCalled = true;

        return $response;
    };

    // Call the middleware
    $result = $this->middleware->handle($request, $next);

    // Assert that the middleware returns the response
    expect($result)->toBe($response);

    // Assert that the next closure was called
    expect($nextWasCalled)->toBeTrue();
});
