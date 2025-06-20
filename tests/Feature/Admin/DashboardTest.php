<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions first
    foreach (['create', 'destroy', 'edit', 'index', 'show', 'store', 'update', '*'] as $action) {
        // Admin Module
        Permission::create(['name' => 'admin.users.'.$action]);
        Permission::create(['name' => 'admin.organizations.'.$action]);

        // Organization Management Module
        Permission::create(['name' => 'orgmgmt.users.'.$action]);
    }

    // Create the Admin role with necessary permissions
    $adminRole = Role::create(['name' => 'Admin']);
    $adminRole->givePermissionTo('admin.users.*');
    $adminRole->givePermissionTo('admin.organizations.*');
    $adminRole->givePermissionTo('orgmgmt.users.*');
});

it('renders organization selection and admin dashboard for admins', function () {
    // Create admin user
    $admin = User::factory()->globalAdmin()->create();

    // Create some organizations
    $organizations = Organization::factory()->count(3)->create();

    // Log in as admin
    $response = $this->actingAs($admin)->get('/start');
    // Organization selection should be rendered with admin option included
    $response->assertInertia(fn (Assert $page): AssertableJson => $page
        ->component('OrganizationSelection')
        ->has('organizations')
        ->where('organizations.0.name', 'Administration')
        ->where('organizations.0.slug', 'admin')
    );

    // Select admin option and go to admin dashboard
    $response = $this->actingAs($admin)->post(route('organization-selection.store'), [
        'organization_id' => GLOBAL_ORG_ID,
    ]);
    // Should redirect to admin dashboard
    $response->assertRedirect('/admin/dashboard');

    // Visit admin dashboard
    $response = $this->actingAs($admin)->get('/admin/dashboard');

    // Verify admin dashboard is rendered
    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/Dashboard')
        );
});

it('shows both admin and organization options to admins', function () {
    // Create admin user with multiple organizations
    $organizations = Organization::factory()->count(2)->create();
    $admin = User::factory()->globalAdmin()->create();
    $admin->organizations()->attach($organizations->pluck('id'));

    // Visit organization selection
    $response = $this->actingAs($admin)->get('/start');
    // Should have admin option plus the organization options
    $response->assertInertia(fn (Assert $page): AssertableJson => $page
        ->component('OrganizationSelection')
        ->has('organizations', $organizations->count() + 1) // +1 for Admin option
        ->where('organizations.0.name', 'Administration')
    );

    // Select one of the organizations
    $organization = $organizations->first();
    $response = $this->actingAs($admin)->post(route('organization-selection.store'), [
        'organization_id' => $organization->id,
    ]);

    // Should redirect to organization dashboard
    $response->assertRedirect('/'.$organization->slug.'/dashboard');

    // Visit organization dashboard
    $response = $this->actingAs($admin)->get('/'.$organization->slug.'/dashboard');
    // Verify organization dashboard is rendered
    $response->assertOk()
        ->assertInertia(fn (Assert $page): AssertableJson => $page
            ->component('orgmgmt/Dashboard')
            ->has('organization')
            ->where('organization.id', $organization->id)
            ->where('organization.slug', $organization->slug)
        );
});
