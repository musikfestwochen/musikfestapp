<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Define the GLOBAL_ORG_ID constant if it's not already defined
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    // Create the necessary permissions
    Permission::create(['name' => 'admin.organizations.index', 'guard_name' => 'web']);
});

it('returns admin slug when selecting GLOBAL_ORG_ID', function () {
    // Create the service
    $service = new OrganizationSelectionService;

    // Process the organization selection with GLOBAL_ORG_ID
    $result = $service->processOrganizationSelection(GLOBAL_ORG_ID);

    // Assert that the result is 'admin'
    expect($result)->toBe('admin');
});

it('returns organizations with admin option for users with admin permissions', function () {
    // Create a user with admin permissions
    $user = User::factory()->create();

    // Set the global organization context for permissions
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create the permission if it doesn't exist
    $permission = Permission::findOrCreate('admin.organizations.index', 'web');

    // Assign the permission to the user
    $user->givePermissionTo($permission);

    // Create some organizations
    $org1 = Organization::factory()->create(['name' => 'Org 1', 'slug' => 'org-1']);
    $org2 = Organization::factory()->create(['name' => 'Org 2', 'slug' => 'org-2']);

    // Login as the admin user
    Auth::login($user);

    // Create the service
    $service = new OrganizationSelectionService;

    // Get organizations for the user
    $organizations = $service->getOrganizationsForUser();

    // Should include all organizations plus the admin option
    expect($organizations)->toHaveCount(3);

    // First organization should be the admin option
    expect($organizations->first()->id)->toBe(GLOBAL_ORG_ID);
    expect($organizations->first()->name)->toBe('Administration');
    expect($organizations->first()->slug)->toBe('admin');

    // Should include the created organizations
    $slugs = $organizations->pluck('slug')->toArray();
    expect($slugs)->toContain('org-1');
    expect($slugs)->toContain('org-2');
});

it('returns only user organizations for users without admin permissions', function () {
    // Create a user without admin permissions
    $user = User::factory()->create();

    // Create some organizations
    $org1 = Organization::factory()->create(['name' => 'Org 1', 'slug' => 'org-1']);
    $org2 = Organization::factory()->create(['name' => 'Org 2', 'slug' => 'org-2']);
    $org3 = Organization::factory()->create(['name' => 'Org 3', 'slug' => 'org-3']);

    // Attach the user to only two organizations
    $user->organizations()->attach([$org1->id, $org3->id]);

    // Login as the user
    Auth::login($user);

    // Create the service
    $service = new OrganizationSelectionService;

    // Get organizations for the user
    $organizations = $service->getOrganizationsForUser();

    // Should include only the organizations the user belongs to
    expect($organizations)->toHaveCount(2);

    // Should include org1 and org3, but not org2
    $slugs = $organizations->pluck('slug')->toArray();
    expect($slugs)->toContain('org-1');
    expect($slugs)->toContain('org-3');
    expect($slugs)->not->toContain('org-2');

    // Should not include the admin option
    expect($slugs)->not->toContain('admin');
});

it('returns organization slug when selecting a valid organization', function () {
    // Create a user
    $user = User::factory()->create();

    // Create an organization
    $organization = Organization::factory()->create(['name' => 'Org 1', 'slug' => 'org-1']);

    // Attach the user to the organization
    $user->organizations()->attach($organization->id);

    // Login as the user
    Auth::login($user);

    // Create the service
    $service = new OrganizationSelectionService;

    // Process the organization selection
    $result = $service->processOrganizationSelection($organization->id);

    // Assert that the result is the organization slug
    expect($result)->toBe('org-1');
});

it('throws an exception when selecting an organization the user does not belong to', function () {
    // Create a user
    $user = User::factory()->create();

    // Create an organization
    $organization = Organization::factory()->create(['name' => 'Org 1', 'slug' => 'org-1']);

    // User is not attached to the organization

    // Login as the user
    Auth::login($user);

    // Create the service
    $service = new OrganizationSelectionService;

    // Process the organization selection should throw an exception
    $service->processOrganizationSelection($organization->id);
})->throws(AuthorizationException::class, 'You do not have access to this organization.');

it('allows admin users to select any organization', function () {
    // Create an admin user
    $user = User::factory()->create();

    // Set the global organization context for permissions
    setPermissionsOrgId(GLOBAL_ORG_ID);

    // Create the permission if it doesn't exist
    $permission = Permission::findOrCreate('admin.organizations.index', 'web');

    // Assign the permission to the user
    $user->givePermissionTo($permission);

    // Create an organization
    $organization = Organization::factory()->create(['name' => 'Org 1', 'slug' => 'org-1']);

    // Admin is not directly attached to the organization

    // Login as admin
    Auth::login($user);

    // Create the service
    $service = new OrganizationSelectionService;

    // Process the organization selection
    $result = $service->processOrganizationSelection($organization->id);

    // Assert that the result is the organization slug
    expect($result)->toBe('org-1');
});
