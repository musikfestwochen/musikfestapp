<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

covers(App\Services\OrganizationSelectionService::class);

describe('OrganizationSelectionService', function () {
    beforeEach(function () {
        if (! defined('GLOBAL_ORG_ID')) {
            define('GLOBAL_ORG_ID', 0);
        }

        $this->service = new OrganizationSelectionService;
    });

    it('returns all orgs plus admin org for admin user', function () {
        $user = User::factory()->create();
        $orgs = Organization::factory()->count(2)->create();
        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);
        $result = $this->service->getOrganizationsForUser();
        expect($result->pluck('slug'))->toContain('admin');
        expect($result->pluck('id'))->toContain(GLOBAL_ORG_ID);
        expect($result->pluck('id'))->toContain($orgs[0]->id);
    });

    it('returns only user orgs for non-admin user', function () {
        $user = User::factory()->create();
        $orgs = Organization::factory()->count(2)->create();
        $user->organizations()->attach($orgs);
        Auth::shouldReceive('user')->andReturn($user);
        $result = $this->service->getOrganizationsForUser();
        expect($result->pluck('id')->toArray())->toBe($orgs->pluck('id')->toArray());
    });

    it('returns admin slug when selecting GLOBAL_ORG_ID', function () {
        $result = $this->service->processOrganizationSelection(GLOBAL_ORG_ID);
        expect($result)->toBe('admin');
    });

    it('returns org slug when user belongs to org', function () {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['slug' => 'org-42']);
        $user->organizations()->attach($org);
        Auth::shouldReceive('user')->andReturn($user);
        // Set organizations relation as IDs for contains() check
        $user->setRelation('organizations', collect([$org->id]));
        $result = $this->service->processOrganizationSelection($org->id);
        expect($result)->toBe('org-42');
    });

    it('throws AuthorizationException when user does not belong and is not admin', function () {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);
        $user->setRelation('organizations', collect([]));
        expect(fn () => $this->service->processOrganizationSelection($org->id))
            ->toThrow(AuthorizationException::class, 'You do not have access to this organization.');
    });

    it('returns org slug when user is admin but not a member', function () {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['slug' => 'org-77']);
        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);
        $user->setRelation('organizations', collect([]));
        $result = $this->service->processOrganizationSelection($org->id);
        expect($result)->toBe('org-77');
    });

    it('returns admin slug when selecting GLOBAL_ORG_ID and user is present', function () {
        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);
        $result = $this->service->processOrganizationSelection(GLOBAL_ORG_ID);
        expect($result)->toBe('admin');
    });

    it('admin user: first organization is always admin org', function () {
        $user = User::factory()->create();
        $orgs = Organization::factory()->count(2)->create();
        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);
        $result = $this->service->getOrganizationsForUser();
        expect($result)->not->toBeEmpty();
        expect($result->first()->slug)->toBe('admin');
        expect($result->first()->id)->toBe(GLOBAL_ORG_ID);
    });

    it('properly creates admin organization with correct properties and mutations', function () {
        $user = User::factory()->create();
        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getOrganizationsForUser();
        $adminOrg = $result->first();

        // Test that admin org has correct properties
        expect($adminOrg->id)->toBe(GLOBAL_ORG_ID);
        expect($adminOrg->name)->toBe('Administration');
        expect($adminOrg->slug)->toBe('admin');

        // Test that the admin org is actually an Organization instance
        expect($adminOrg)->toBeInstanceOf(Organization::class);

        // Test that the id mutation was applied correctly
        expect($adminOrg->getAttributes()['id'])->toBe(GLOBAL_ORG_ID);
    });

    it('admin organization appears first regardless of other organizations order', function () {
        $user = User::factory()->create();
        // Create organizations with specific names to test ordering
        $org1 = Organization::factory()->create(['name' => 'Alpha Organization']);
        $org2 = Organization::factory()->create(['name' => 'Beta Organization']);

        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getOrganizationsForUser();

        // Admin org should always be first
        expect($result->first()->slug)->toBe('admin');
        expect($result->first()->id)->toBe(GLOBAL_ORG_ID);
        expect($result->first()->name)->toBe('Administration');

        // Other organizations should follow
        expect($result->count())->toBe(3); // admin + 2 created orgs
        expect($result->pluck('id'))->toContain($org1->id);
        expect($result->pluck('id'))->toContain($org2->id);
    });

    it('admin organization id mutation overrides constructor value', function () {
        $user = User::factory()->create();
        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getOrganizationsForUser();
        $adminOrg = $result->first();

        // The explicit id assignment should ensure the id is set correctly
        // even if the constructor initially set it differently
        expect($adminOrg->id)->toBe(GLOBAL_ORG_ID);
        expect($adminOrg->getKey())->toBe(GLOBAL_ORG_ID);
    });

    it('admin organization constructor receives id parameter to ensure proper model state', function () {
        $user = User::factory()->create();
        setPermissionsOrgId(GLOBAL_ORG_ID);
        Permission::findOrCreate('admin.organizations.index');
        $user->givePermissionTo('admin.organizations.index');
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getOrganizationsForUser();
        $adminOrg = $result->first();

        // Verify the organization is correctly constructed with all required attributes
        expect($adminOrg->id)->toBe(GLOBAL_ORG_ID);
        expect($adminOrg->name)->toBe('Administration');
        expect($adminOrg->slug)->toBe('admin');

        // Test that the model is in a consistent state - both the id property and the internal attributes
        // should reflect the GLOBAL_ORG_ID, which ensures the constructor array item is important
        expect($adminOrg->getKey())->toBe(GLOBAL_ORG_ID);
        expect($adminOrg->getAttribute('id'))->toBe(GLOBAL_ORG_ID);

        // Test that the model behaves correctly for serialization scenarios
        // where the constructor array matters
        $serialized = $adminOrg->toArray();
        expect($serialized['id'])->toBe(GLOBAL_ORG_ID);
        expect($serialized['name'])->toBe('Administration');
        expect($serialized['slug'])->toBe('admin');
    });
});
