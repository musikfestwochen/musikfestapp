<?php

use App\Http\Middleware\Permissions\OrganizationSlugMiddleware;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    // Define the GLOBAL_ORG_ID constant if it's not already defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    // Create the necessary permissions
    Permission::create(['name' => 'admin.organizations.index', 'guard_name' => 'web']);
});

it('handles non object organization parameter', function () {
    // Create a middleware instance
    $middleware = new OrganizationSlugMiddleware;

    // Set a different organization context to verify it changes
    $differentOrgId = 999;
    setPermissionsOrgId($differentOrgId);

    // Create a request with a non-object organization parameter
    $request = Request::create('/some-slug/dashboard', 'GET');
    $request->setRouteResolver(function () {
        return new Route('GET', '/{organization}/dashboard', function (): Response {
            return new Response;
        })->bind(request());
    });
    $request->route()->setParameter('organization', 'some-slug'); // String instead of object

    // Run the middleware
    $response = $middleware->handle($request, function ($req): Response {
        return new Response('Test Response');
    });

    // Assert that the response is passed through
    expect($response->getContent())->toBe('Test Response');

    // The organization context should be set to GLOBAL_ORG_ID
    expect(getPermissionsOrgId())->toBe(GLOBAL_ORG_ID);
});

it('sets the correct organization context when accessing organization routes', function () {
    // Create a user with different organizations
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'Test Org', 'slug' => 'test-org']);
    $user->organizations()->attach($organization->id);

    // Login as the user
    $this->actingAs($user);

    // Visit the organization dashboard
    $response = $this->get(sprintf('/%s/dashboard', $organization->slug));

    // Assert the response is successful
    $response->assertStatus(200);

    // The middleware should have set the organization context
    // We can't directly test this in a feature test, but we can verify the page renders correctly
    $response->assertSee($organization->name);
});

it('handles non existent organization slugs correctly', function () {
    // Create a user
    $user = User::factory()->create();

    // Login as the user
    $this->actingAs($user);

    // Visit a non-existent organization's dashboard
    $response = $this->get('/non-existent-org/dashboard');

    // Should return 404 Not Found
    $response->assertStatus(404);
});

it('allows admin users to access any organization', function () {
    // Create an admin user
    $user = User::factory()->create();

    // Set the global organization context for permissions
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create the permission if it doesn't exist
    $permission = Permission::findOrCreate('admin.organizations.index', 'web');

    // Assign the permission to the user
    $user->givePermissionTo($permission);

    // Create an organization
    $organization = Organization::factory()->create(['name' => 'Test Org', 'slug' => 'test-org']);

    // Admin is not directly attached to the organization

    // Login as admin
    $this->actingAs($user);

    // Visit the organization dashboard
    $response = $this->get(sprintf('/%s/dashboard', $organization->slug));

    // Should be able to access it
    $response->assertStatus(200);

    // The page should contain the organization name
    $response->assertSee($organization->name);
});

it('sets global org id when no organization parameter is present', function () {
    // Create a middleware instance
    $middleware = new OrganizationSlugMiddleware;

    // Set a different organization context to verify it changes
    $differentOrgId = 999;
    setPermissionsOrgId($differentOrgId);

    // Create a request with no organization parameter
    $request = Request::create('/some-route', 'GET');
    $request->setRouteResolver(function () {
        return new Route('GET', '/some-route', function (): Response {
            return new Response;
        })->bind(request());
    });

    // Run the middleware
    $response = $middleware->handle($request, function ($req): Response {
        return new Response('Test Response');
    });

    // Assert that the response is passed through
    expect($response->getContent())->toBe('Test Response');

    // The organization context should be set to GLOBAL_ORG_ID
    expect(getPermissionsOrgId())->toBe(GLOBAL_ORG_ID);
});

it('sets global org id when organization is object without id property', function () {
    // Create a middleware instance
    $middleware = new OrganizationSlugMiddleware;

    // Set a different organization context to verify it changes
    $differentOrgId = 999;
    setPermissionsOrgId($differentOrgId);

    // Create a request with an organization parameter that is an object without id property
    $request = Request::create('/some-object/dashboard', 'GET');
    $request->setRouteResolver(function () {
        return new Route('GET', '/{organization}/dashboard', function (): Response {
            return new Response;
        })->bind(request());
    });

    // Create a stdClass object without id property
    $objectWithoutId = new stdClass;
    $request->route()->setParameter('organization', $objectWithoutId);

    // Run the middleware
    $response = $middleware->handle($request, function ($req): Response {
        return new Response('Test Response');
    });

    // Assert that the response is passed through
    expect($response->getContent())->toBe('Test Response');

    // The organization context should be set to GLOBAL_ORG_ID
    expect(getPermissionsOrgId())->toBe(GLOBAL_ORG_ID);
});

it('sets organization id when organization is object with id property', function () {
    // Create a middleware instance
    $middleware = new OrganizationSlugMiddleware;

    // Set a different organization context to verify it changes
    $differentOrgId = 999;
    setPermissionsOrgId($differentOrgId);

    // Create an organization with a specific ID
    $organization = Organization::factory()->create(['id' => 123]);

    // Create a request
    $request = Request::create('/test-org/dashboard', 'GET');
    $request->setRouteResolver(function () use ($organization): Route {
        $route = new Route('GET', '/{organization}/dashboard', function (): Response {
            return new Response;
        });
        $route->bind(request());
        $route->setParameter('organization', $organization);

        return $route;
    });

    // Run the middleware
    $response = $middleware->handle($request, function ($req): Response {
        return new Response('Test Response');
    });

    // Assert that the response is passed through
    expect($response->getContent())->toBe('Test Response');

    // The organization context should be set to the organization's id
    expect(getPermissionsOrgId())->toBe(123);
});

it('sets global org id when organization parameter is null', function () {
    // Create a middleware instance
    $middleware = new OrganizationSlugMiddleware;

    // Set a different organization context to verify it changes
    $differentOrgId = 999;
    setPermissionsOrgId($differentOrgId);

    // Create a request with a null organization parameter
    $request = Request::create('/null-org/dashboard', 'GET');
    $request->setRouteResolver(function () {
        return new Route('GET', '/{organization}/dashboard', function (): Response {
            return new Response;
        })->bind(request());
    });

    // Set the organization parameter to null
    $request->route()->setParameter('organization', null);

    // Run the middleware
    $response = $middleware->handle($request, function ($req): Response {
        return new Response('Test Response');
    });

    // Assert that the response is passed through
    expect($response->getContent())->toBe('Test Response');

    // The organization context should be set to GLOBAL_ORG_ID
    expect(getPermissionsOrgId())->toBe(GLOBAL_ORG_ID);
});
