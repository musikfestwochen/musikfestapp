<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSelectionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create permissions first
    foreach (['create', 'destroy', 'edit', 'index', 'show', 'store', 'update', '*'] as $action) {
        // Admin Module
        Permission::create(['name' => 'admin.users.'.$action]);
        Permission::create(['name' => 'admin.organizations.'.$action]);

        // Organization Management Module
        Permission::create(['name' => 'orgmgmt.users.'.$action]);
    }

    // Create the necessary roles
    $userRole = Role::create(['name' => 'OrganizationAdmin']);
    $userRole->givePermissionTo('orgmgmt.users.*');
});

it('redirects directly to organization dashboard when user has only one organization', function () {
    // Create a user with organization admin role and a single organization
    $organization = Organization::factory()->create();
    $user = User::factory()->organizationAdmin($organization)->create();

    // Mock the organization selection service
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('getOrganizationsForUser')
            ->once()
            ->andReturn(new Collection([$organization]));
    });

    // Log in as the user
    $response = $this->actingAs($user)->get('/start');

    // Should redirect directly to the organization dashboard
    $response->assertRedirect('/'.$organization->slug.'/dashboard');

    // Visit the organization dashboard
    $response = $this->actingAs($user)->get('/'.$organization->slug.'/dashboard');
    // Verify organization dashboard is rendered
    $response->assertOk()
        ->assertInertia(fn (Assert $page): AssertableJson => $page
            ->component('orgmgmt/Dashboard')
            ->has('organization')
            ->where('organization.id', $organization->id)
            ->where('organization.slug', $organization->slug)
        );
});

it('shows organization selection and then dashboard when user has multiple organizations', function () {
    // Create organizations and a user with an organization admin role
    $organizations = Organization::factory()->count(2)->create();
    $user = User::factory()->organizationAdmin(null, $organizations->pluck('id')->toArray())->create();

    // Mock the organization selection service
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organizations) {
        $mock->shouldReceive('getOrganizationsForUser')
            ->once()
            ->andReturn(new Collection($organizations));
    });

    // Log in as the user
    $response = $this->actingAs($user)->get('/start');
    // Should render organization selection
    $response->assertInertia(fn (Assert $page): AssertableJson => $page
        ->component('OrganizationSelection')
        ->has('organizations', $organizations->count())
    );

    // Select one organization
    $organization = $organizations->first();

    // Mock the organization selection service for processing selection
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('processOrganizationSelection')
            ->once()
            ->with($organization->id)
            ->andReturn($organization->slug);
    });

    // Select the organization
    $response = $this->actingAs($user)->post(route('organization-selection.store'), [
        'organization_id' => $organization->id,
    ]);

    // Should redirect to the organization dashboard
    $response->assertRedirect('/'.$organization->slug.'/dashboard');

    // Visit the organization dashboard
    $response = $this->actingAs($user)->get('/'.$organization->slug.'/dashboard');

    // Verify organization dashboard is rendered
    $response->assertOk()
        ->assertInertia(fn (Assert $page): AssertableJson => $page
            ->component('orgmgmt/Dashboard')
            ->has('organization')
            ->where('organization.id', $organization->id)
            ->where('organization.slug', $organization->slug)
        );
});
