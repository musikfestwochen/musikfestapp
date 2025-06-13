<?php

namespace Tests\Feature\Middleware\Permissions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OrganizationSlugMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Define the GLOBAL_ORG_ID constant if it's not already defined
        if (! defined('GLOBAL_ORG_ID')) {
            define('GLOBAL_ORG_ID', 0);
        }

        // Create the necessary permissions
        Permission::create(['name' => 'admin.organizations.index', 'guard_name' => 'web']);
    }

    /** @test */
    public function it_handles_non_object_organization_parameter(): void
    {
        // Create a middleware instance
        $middleware = new \App\Http\Middleware\Permissions\OrganizationSlugMiddleware;

        // Set a different organization context to verify it changes
        $differentOrgId = 999;
        setPermissionsOrgId($differentOrgId);

        // Create a request with a non-object organization parameter
        $request = \Illuminate\Http\Request::create('/some-slug/dashboard', 'GET');
        $request->setRouteResolver(function () {
            return new \Illuminate\Routing\Route('GET', '/{organization}/dashboard', function (): \Symfony\Component\HttpFoundation\Response {
                return new Response;
            })->bind(request());
        });
        $request->route()->setParameter('organization', 'some-slug'); // String instead of object

        // Run the middleware
        $response = $middleware->handle($request, function ($req): \Symfony\Component\HttpFoundation\Response {
            return new Response('Test Response');
        });

        // Assert that the response is passed through
        $this->assertEquals('Test Response', $response->getContent());

        // The organization context should be set to GLOBAL_ORG_ID
        $this->assertEquals(GLOBAL_ORG_ID, getPermissionsOrgId());
    }

    /** @test */
    public function it_sets_the_correct_organization_context_when_accessing_organization_routes(): void
    {
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
    }

    /** @test */
    public function it_handles_non_existent_organization_slugs_correctly(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Login as the user
        $this->actingAs($user);

        // Visit a non-existent organization's dashboard
        $response = $this->get('/non-existent-org/dashboard');

        // Should return 404 Not Found
        $response->assertStatus(404);
    }

    /** @test */
    public function it_allows_admin_users_to_access_any_organization(): void
    {
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
    }

    /** @test */
    public function it_sets_global_org_id_when_no_organization_parameter_is_present(): void
    {
        // Create a middleware instance
        $middleware = new \App\Http\Middleware\Permissions\OrganizationSlugMiddleware;

        // Set a different organization context to verify it changes
        $differentOrgId = 999;
        setPermissionsOrgId($differentOrgId);

        // Create a request with no organization parameter
        $request = \Illuminate\Http\Request::create('/some-route', 'GET');
        $request->setRouteResolver(function () {
            return new \Illuminate\Routing\Route('GET', '/some-route', function (): \Symfony\Component\HttpFoundation\Response {
                return new Response;
            })->bind(request());
        });

        // Run the middleware
        $response = $middleware->handle($request, function ($req): \Symfony\Component\HttpFoundation\Response {
            return new Response('Test Response');
        });

        // Assert that the response is passed through
        $this->assertEquals('Test Response', $response->getContent());

        // The organization context should be set to GLOBAL_ORG_ID
        $this->assertEquals(GLOBAL_ORG_ID, getPermissionsOrgId());
    }

    /** @test */
    public function it_sets_global_org_id_when_organization_is_object_without_id_property(): void
    {
        // Create a middleware instance
        $middleware = new \App\Http\Middleware\Permissions\OrganizationSlugMiddleware;

        // Set a different organization context to verify it changes
        $differentOrgId = 999;
        setPermissionsOrgId($differentOrgId);

        // Create a request with an organization parameter that is an object without id property
        $request = \Illuminate\Http\Request::create('/some-object/dashboard', 'GET');
        $request->setRouteResolver(function () {
            return new \Illuminate\Routing\Route('GET', '/{organization}/dashboard', function (): \Symfony\Component\HttpFoundation\Response {
                return new Response;
            })->bind(request());
        });

        // Create a stdClass object without id property
        $objectWithoutId = new \stdClass;
        $request->route()->setParameter('organization', $objectWithoutId);

        // Run the middleware
        $response = $middleware->handle($request, function ($req): \Symfony\Component\HttpFoundation\Response {
            return new Response('Test Response');
        });

        // Assert that the response is passed through
        $this->assertEquals('Test Response', $response->getContent());

        // The organization context should be set to GLOBAL_ORG_ID
        $this->assertEquals(GLOBAL_ORG_ID, getPermissionsOrgId());
    }

    /** @test */
    public function it_sets_organization_id_when_organization_is_object_with_id_property(): void
    {
        // Create a middleware instance
        $middleware = new \App\Http\Middleware\Permissions\OrganizationSlugMiddleware;

        // Set a different organization context to verify it changes
        $differentOrgId = 999;
        setPermissionsOrgId($differentOrgId);

        // Create an organization with a specific ID
        $organization = \App\Models\Organization::factory()->create(['id' => 123]);

        // Create a request
        $request = \Illuminate\Http\Request::create('/test-org/dashboard', 'GET');
        $request->setRouteResolver(function () use ($organization): \Illuminate\Routing\Route {
            $route = new \Illuminate\Routing\Route('GET', '/{organization}/dashboard', function (): \Symfony\Component\HttpFoundation\Response {
                return new Response;
            });
            $route->bind(request());
            $route->setParameter('organization', $organization);

            return $route;
        });

        // Run the middleware
        $response = $middleware->handle($request, function ($req): \Symfony\Component\HttpFoundation\Response {
            return new Response('Test Response');
        });

        // Assert that the response is passed through
        $this->assertEquals('Test Response', $response->getContent());

        // The organization context should be set to the organization's id
        $this->assertEquals(123, getPermissionsOrgId());
    }

    /** @test */
    public function it_sets_global_org_id_when_organization_parameter_is_null(): void
    {
        // Create a middleware instance
        $middleware = new \App\Http\Middleware\Permissions\OrganizationSlugMiddleware;

        // Set a different organization context to verify it changes
        $differentOrgId = 999;
        setPermissionsOrgId($differentOrgId);

        // Create a request with a null organization parameter
        $request = \Illuminate\Http\Request::create('/null-org/dashboard', 'GET');
        $request->setRouteResolver(function () {
            return new \Illuminate\Routing\Route('GET', '/{organization}/dashboard', function (): \Symfony\Component\HttpFoundation\Response {
                return new Response;
            })->bind(request());
        });

        // Set the organization parameter to null
        $request->route()->setParameter('organization', null);

        // Run the middleware
        $response = $middleware->handle($request, function ($req): \Symfony\Component\HttpFoundation\Response {
            return new Response('Test Response');
        });

        // Assert that the response is passed through
        $this->assertEquals('Test Response', $response->getContent());

        // The organization context should be set to GLOBAL_ORG_ID
        $this->assertEquals(GLOBAL_ORG_ID, getPermissionsOrgId());
    }
}
