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
});
