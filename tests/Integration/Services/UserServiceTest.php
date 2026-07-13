<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

covers(UserService::class);

beforeEach(function () {
    if (! defined('GLOBAL_ORG_ID')) {
        define('GLOBAL_ORG_ID', 0);
    }

    $this->service = new UserService;
});

describe('getUsers', function () {
    it('returns users', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);
        User::factory()->count(15)->create();

        $result = $this->service->getUsers();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(15);
    });

    it('filters users by organization when not in global organization', function () {
        $organization = Organization::factory()->create();
        setPermissionsOrgId($organization->id);

        $userInOrg = User::factory()->create(['name' => 'User In Org']);
        $userNotInOrg = User::factory()->create(['name' => 'User Not In Org']);

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getUsers();

        expect($result->count())->toBe(1);
    });

    it('returns all users when in global organization', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $organization = Organization::factory()->create();
        $userInOrg = User::factory()->create(['name' => 'User In Org']);
        $userNotInOrg = User::factory()->create(['name' => 'User Not In Org']);

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getUsers();

        expect($result->count())->toBe(2);
    });

    it('returns empty collection when no users exist', function () {
        setPermissionsOrgId(GLOBAL_ORG_ID);

        $result = $this->service->getUsers();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('filters correctly when organization has no users', function () {
        $organization = Organization::factory()->create();
        setPermissionsOrgId($organization->id);

        User::factory()->count(3)->create(); // Users not attached to any organization

        $result = $this->service->getUsers();

        expect($result->count())->toBe(0);
    });

    it('uses strict comparison for GLOBAL_ORG_ID check', function ($orgId, $expectedCount) {
        // Test that string "0" is not treated as integer 0
        setPermissionsOrgId($orgId); // String zero instead of integer zero

        $organization = Organization::factory()->create();
        $userInOrg = User::factory()->create();
        $userNotInOrg = User::factory()->create();

        $userInOrg->organizations()->attach($organization);

        $result = $this->service->getUsers();

        // With loose comparison (!=), "0" == 0, so it returns all users (no filtering)
        // With strict comparison (!==), "0" !== 0, so it would filter by organization
        expect($result->count())->toBe($expectedCount);
    })->with(
        [
            [GLOBAL_ORG_ID, 2],
            [Str(GLOBAL_ORG_ID), 0], // String "0" should not match integer 0
        ]
    );

    it('properly filters users when multiple organizations exist', function () {
        $targetOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        setPermissionsOrgId($targetOrg->id);

        $userInTargetOrg = User::factory()->create(['name' => 'User In Target Org']);
        $userInOtherOrg = User::factory()->create(['name' => 'User In Other Org']);
        $userInBothOrgs = User::factory()->create(['name' => 'User In Both Orgs']);
        $userInNoOrg = User::factory()->create(['name' => 'User In No Org']);

        $userInTargetOrg->organizations()->attach($targetOrg);
        $userInOtherOrg->organizations()->attach($otherOrg);
        $userInBothOrgs->organizations()->attach([$targetOrg->id, $otherOrg->id]);

        $result = $this->service->getUsers();

        // Should only return users that belong to the target organization
        expect($result->count())->toBe(2)
            ->and($result->pluck('name')->toArray())->toContain('User In Target Org')
            ->and($result->pluck('name')->toArray())->toContain('User In Both Orgs')
            ->and($result->pluck('name')->toArray())->not->toContain('User In Other Org')
            ->and($result->pluck('name')->toArray())->not->toContain('User In No Org');
    });

    it('enforces organization ID matching in whereHas clause', function () {
        $correctOrg = Organization::factory()->create();
        $wrongOrg = Organization::factory()->create();
        setPermissionsOrgId($correctOrg->id);

        $userInCorrectOrg = User::factory()->create();
        $userInWrongOrg = User::factory()->create();

        $userInCorrectOrg->organizations()->attach($correctOrg);
        $userInWrongOrg->organizations()->attach($wrongOrg);

        $result = $this->service->getUsers();

        // This test specifically verifies that the where clause filters by organization ID
        // If the where clause is removed, this test should fail
        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(1)
            ->and($result->pluck('id')->toArray())->toContain($userInCorrectOrg->id)
            ->and($result->pluck('id')->toArray())->not->toContain($userInWrongOrg->id);
    });

    it('filters by explicit organization when organization parameter is provided (no columns)', function () {
        // Create two organizations and users
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        setPermissionsOrgId(GLOBAL_ORG_ID); // permissions context should not matter when explicit org is passed

        $userA1 = User::factory()->create(['name' => 'A1']);
        $userB1 = User::factory()->create(['name' => 'B1']);

        $userA1->organizations()->attach($orgA);
        $userB1->organizations()->attach($orgB);

        $result = $this->service->getUsers($orgA);

        expect($result->pluck('id')->toArray())->toContain($userA1->id)
            ->and($result->pluck('id')->toArray())->not->toContain($userB1->id);
    });

    it('applies select columns when organization parameter is provided', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId(GLOBAL_ORG_ID);

        // Ensure phone is set so we can verify it is not selected
        $user = User::factory()->create(['phone' => '+41000000000', 'email' => 'a@example.test']);
        $user->organizations()->attach($org);

        $columns = ['users.id', 'users.name', 'users.email'];
        $result = $this->service->getUsers($org, $columns);

        expect($result->count())->toBe(1);
        $attrs = $result->first()->getAttributes();
        expect(array_keys($attrs))->toEqualCanonicalizing(['id', 'name', 'email']);
    });

    it('applies select columns in fallback branch (no organization argument)', function () {
        $org = Organization::factory()->create();
        setPermissionsOrgId($org->id); // non-global, triggers whereHas

        $userInOrg = User::factory()->create(['phone' => '+41000000001']);
        $userOut = User::factory()->create(['phone' => '+41000000002']);
        $userInOrg->organizations()->attach($org);

        $columns = ['users.id'];
        $result = $this->service->getUsers(null, $columns);

        expect($result->count())->toBe(1)
            ->and($result->first()->id)->toBe($userInOrg->id);
        $attrs = $result->first()->getAttributes();
        expect(array_keys($attrs))->toEqualCanonicalizing(['id']);
    });
});

it('ignores permissions org context when explicit organization is provided', function () {
    // Create two organizations: A (explicit) and B (permissions context)
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    // Set permissions context to a non-global org different from explicit org
    setPermissionsOrgId($orgB->id);

    // Create users and attach only to orgA
    $userInA = User::factory()->create(['name' => 'OnlyInA']);
    $userInA->organizations()->attach($orgA);

    // Sanity: also a user only in orgB to ensure no bleed-through
    $userInB = User::factory()->create(['name' => 'OnlyInB']);
    $userInB->organizations()->attach($orgB);

    // When explicit org is provided, permissions context must be ignored
    $result = $this->service->getUsers($orgA);

    expect($result->pluck('id')->toArray())->toContain($userInA->id)
        ->and($result->pluck('id')->toArray())->not->toContain($userInB->id)
        ->and($result->count())->toBe(1);
});

describe('removeFromOrganization', function () {
    it('deletes a user who only belongs to the organization', function () {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id);

        $deleted = $this->service->removeFromOrganization($user, $organization);

        expect($deleted)->toBeTrue();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

    it('detaches a user who belongs to other organizations', function () {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach([$organization->id, $otherOrganization->id]);

        $deleted = $this->service->removeFromOrganization($user, $organization);

        expect($deleted)->toBeFalse();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $otherOrganization->id, 'user_id' => $user->id]);
    });

    it('removes only the current organization roles and direct permissions when detaching', function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach([$organization->id, $otherOrganization->id]);
        $viewerRole = Role::findByName('PeopleCountViewer');
        $adminRole = Role::findByName('OrganizationAdmin');
        $sensorPermission = Permission::findOrCreate('peoplecount.sensors.index');
        $eventPermission = Permission::findOrCreate('peoplecount.events.index');

        setPermissionsOrgId($organization->id);
        $user->assignRole($viewerRole);
        $user->givePermissionTo($sensorPermission);

        setPermissionsOrgId($otherOrganization->id);
        $user->assignRole($adminRole);
        $user->givePermissionTo($eventPermission);

        $this->service->removeFromOrganization($user, $organization);

        $this->assertDatabaseMissing('model_has_roles', [
            'organization_id' => $organization->id,
            'role_id' => $viewerRole->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
        $this->assertDatabaseMissing('model_has_permissions', [
            'organization_id' => $organization->id,
            'permission_id' => $sensorPermission->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'organization_id' => $otherOrganization->id,
            'role_id' => $adminRole->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
        $this->assertDatabaseHas('model_has_permissions', [
            'organization_id' => $otherOrganization->id,
            'permission_id' => $eventPermission->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
    });
});

describe('syncOrganizations', function () {
    it('syncs user organizations without deleting the user', function () {
        $oldOrganization = Organization::factory()->create();
        $newOrganization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($oldOrganization->id);

        $this->service->syncOrganizations($user, [$newOrganization->id]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('organization_user', ['organization_id' => $oldOrganization->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $newOrganization->id, 'user_id' => $user->id]);
    });

    it('removes access for detached organizations', function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $oldOrganization = Organization::factory()->create();
        $newOrganization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($oldOrganization->id);
        $role = Role::findByName('PeopleCountViewer');
        $permission = Permission::findOrCreate('peoplecount.sensors.index');

        setPermissionsOrgId($oldOrganization->id);
        $user->assignRole($role);
        $user->givePermissionTo($permission);

        $this->service->syncOrganizations($user, [$newOrganization->id]);

        $this->assertDatabaseMissing('model_has_roles', [
            'organization_id' => $oldOrganization->id,
            'role_id' => $role->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
        $this->assertDatabaseMissing('model_has_permissions', [
            'organization_id' => $oldOrganization->id,
            'permission_id' => $permission->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
    });
});

describe('createOrAttachToOrganization', function () {
    it('creates a new user and attaches them to the organization', function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $organization = Organization::factory()->create();

        $user = $this->service->createOrAttachToOrganization($organization, [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'phone' => null,
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new-user@example.com']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id]);
    });

    it('syncs roles when creating or attaching a user', function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $organization = Organization::factory()->create();
        $viewerRole = Role::findByName('PeopleCountViewer');
        $adminRole = Role::findByName('OrganizationAdmin');

        $user = $this->service->createOrAttachToOrganization($organization, [
            'name' => 'Role User',
            'email' => 'role-user@example.com',
            'phone' => null,
        ], ['PeopleCountViewer', 'OrganizationAdmin']);

        foreach ([$viewerRole, $adminRole] as $role) {
            $this->assertDatabaseHas('model_has_roles', [
                'organization_id' => $organization->id,
                'role_id' => $role->id,
                'model_id' => $user->id,
                'model_type' => User::class,
            ]);
        }
    });

    it('attaches an existing user by email without updating them', function () {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing-user@example.com',
            'phone' => '+41790000000',
        ]);

        $result = $this->service->createOrAttachToOrganization($organization, [
            'name' => 'Updated User',
            'email' => 'existing-user@example.com',
            'phone' => '+41 79 222 22 22',
        ]);

        expect($result->is($user))->toBeTrue();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Existing User', 'phone' => '+41790000000']);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $user->id]);
    });
});
