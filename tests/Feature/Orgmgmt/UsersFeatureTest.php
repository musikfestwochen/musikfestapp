<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('shows the users index page for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $users = User::factory()->sequence(['name' => 'Assigned User'], ['name' => 'No Role User'])->count(2)->create();
    $org->users()->attach($users->pluck('id'));
    $otherOrg->users()->attach($users->first()->id);

    setPermissionsOrgId($org->id);
    $users->first()->assignRole(['PeopleCountViewer', 'OrganizationAdmin']);
    setPermissionsOrgId($otherOrg->id);
    $users->first()->assignRole('StageSafetyViewer');

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.index', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/Users')
            ->where('organization.id', $org->id)
            ->has('users', 2)
            ->where('users.0.name', 'Assigned User')
            ->where('users.0.organization_roles.0.name', 'PeopleCountViewer')
            ->where('users.0.organization_roles.0.display_name', 'People count viewer')
            ->where('users.0.organization_roles.1.name', 'OrganizationAdmin')
            ->has('users.0.organization_roles', 2)
            ->where('users.1.name', 'No Role User')
            ->where('users.1.organization_roles', [])
        );
});

it('shows the create user form for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.create', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/NewUser')
            ->where('organization.id', $org->id)
            ->where('availableRoles.0.name', 'PeopleCountViewer')
            ->where('availableRoles.0.display_name', 'People count viewer')
            ->where('availableRoles.0.description', 'Can view people-count dashboards and data.')
            ->where('availableRoles.1.name', 'StageSafetyViewer')
            ->where('availableRoles.1.display_name', 'Stage Safety viewer')
            ->where('availableRoles.1.description', 'Can view Stage Safety monitoring data.')
            ->where('availableRoles.2.name', 'OrganizationAdmin')
            ->where('availableRoles.2.display_name', 'Organization administrator')
            ->where('availableRoles.2.description', 'Has all organization level permissions over all modules.')
            ->where('selectedRoles', ['PeopleCountViewer'])
        );
});

it('shows the edit user form for an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);
    setPermissionsOrgId($org->id);
    $user->assignRole('OrganizationAdmin');

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.edit', ['organization' => $org->slug, 'user' => $user->id]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/EditUser')
            ->where('organization.id', $org->id)
            ->where('user.id', $user->id)
            ->where('availableRoles.0.name', 'PeopleCountViewer')
            ->where('availableRoles.1.name', 'StageSafetyViewer')
            ->where('availableRoles.2.name', 'OrganizationAdmin')
            ->where('selectedRoles', ['OrganizationAdmin'])
        );
});

it('redirects show to edit for an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create(['phone' => '+41790000000']);
    $org->users()->attach($user->id);

    $response = $this->actingAs($admin)
        ->get(route('orgmgmt.users.show', ['organization' => $org->slug, 'user' => $user->id]));
    $response->assertRedirect(route('orgmgmt.users.edit', ['organization' => $org->slug, 'user' => $user->id]));
});

it('forbids guests from accessing orgmgmt users index', function () {
    $org = Organization::factory()->create();
    $this->get(route('orgmgmt.users.index', ['organization' => $org->slug]))
        ->assertRedirect('/login');
});

it('can create a user for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $userData = User::factory()->make()->toArray();
    unset($userData['email_verified_at']);

    $response = $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), $userData);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', ['email' => $userData['email']]);
    $this->assertDatabaseHas('organization_user', ['organization_id' => $org->id, 'user_id' => User::query()->where('email', $userData['email'])->firstOrFail()->id]);
});

it('attaches an existing user when creating by email for an organization without updating them', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $existingUser = User::factory()->create([
        'name' => 'Existing Name',
        'email' => 'existing@example.com',
        'phone' => '+41790000000',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => 'Updated Existing',
            'email' => 'existing@example.com',
            'phone' => '+41 79 111 11 11',
        ]);

    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
    $this->assertDatabaseHas('users', [
        'id' => $existingUser->id,
        'name' => 'Existing Name',
        'phone' => '+41790000000',
    ]);
    $this->assertDatabaseHas('organization_user', ['organization_id' => $org->id, 'user_id' => $existingUser->id]);
});

it('attaches an unverified existing user when creating by email for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $existingUser = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => $existingUser->name,
            'email' => $existingUser->email,
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $this->assertDatabaseHas('organization_user', ['organization_id' => $org->id, 'user_id' => $existingUser->id]);
});

it('idempotently attaches an existing organization user when creating by email', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $existingUser = User::factory()->create(['email' => 'member@example.com']);
    $existingUser->organizations()->attach($org->id);

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => $existingUser->name,
            'email' => $existingUser->email,
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    expect($existingUser->organizations()->whereKey($org->id)->count())->toBe(1);
});

it('assigns the default viewer role when attaching an existing user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $existingUser = User::factory()->create(['email' => 'viewer@example.com']);
    $viewerRole = Role::findByName('PeopleCountViewer');

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => $existingUser->name,
            'email' => $existingUser->email,
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $viewerRole->id,
        'model_id' => $existingUser->id,
        'model_type' => User::class,
    ]);
});

it('assigns multiple roles when creating an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $viewerRole = Role::findByName('PeopleCountViewer');
    $adminRole = Role::findByName('OrganizationAdmin');

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => 'Role User',
            'email' => 'role-user@example.com',
            'roles' => ['PeopleCountViewer', 'OrganizationAdmin'],
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $user = User::query()->where('email', 'role-user@example.com')->firstOrFail();

    foreach ([$viewerRole, $adminRole] as $role) {
        $this->assertDatabaseHas('model_has_roles', [
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
    }

    $this->assertDatabaseMissing('model_has_permissions', [
        'organization_id' => $org->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
});

it('assigns the Stage Safety viewer role to an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $viewerRole = Role::findByName('StageSafetyViewer');

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => 'Stage Safety User',
            'email' => 'stage-safety@example.com',
            'roles' => ['StageSafetyViewer'],
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $user = User::query()->where('email', 'stage-safety@example.com')->firstOrFail();

    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $viewerRole->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
});

it('rejects global roles when creating an organization user', function (string $role): void {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => 'Global Role User',
            'email' => 'global-role-user@example.com',
            'roles' => [$role],
        ])
        ->assertSessionHasErrors('roles.0');
})->with(['SuperAdmin', 'Admin']);

it('prevents organization admins from changing their own roles when creating by their email', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $adminRole = Role::findByName('OrganizationAdmin');

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['PeopleCountViewer'],
        ])
        ->assertSessionHasErrors('roles');

    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $adminRole->id,
        'model_id' => $admin->id,
        'model_type' => User::class,
    ]);
});

it('syncs multiple roles when updating an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);
    $viewerRole = Role::findByName('PeopleCountViewer');
    $adminRole = Role::findByName('OrganizationAdmin');

    setPermissionsOrgId($org->id);
    $user->assignRole($viewerRole);

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['PeopleCountViewer', 'OrganizationAdmin'],
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    foreach ([$viewerRole, $adminRole] as $role) {
        $this->assertDatabaseHas('model_has_roles', [
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
    }
});

it('rejects global roles when updating an organization user', function (string $role): void {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [$role],
        ])
        ->assertSessionHasErrors('roles.0');
})->with(['SuperAdmin', 'Admin']);

it('allows organization admins to assign organization admin to another user', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);
    $viewerRole = Role::findByName('PeopleCountViewer');
    $adminRole = Role::findByName('OrganizationAdmin');

    setPermissionsOrgId($org->id);
    $user->assignRole($viewerRole);

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['PeopleCountViewer', 'OrganizationAdmin'],
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $adminRole->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
});

it('allows organization admins to remove organization admin from another user', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);
    $viewerRole = Role::findByName('PeopleCountViewer');
    $adminRole = Role::findByName('OrganizationAdmin');

    setPermissionsOrgId($org->id);
    $user->assignRole([$viewerRole, $adminRole]);

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['PeopleCountViewer'],
        ])
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $viewerRole->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
    $this->assertDatabaseMissing('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $adminRole->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
});

it('prevents organization admins from changing their own roles', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $adminRole = Role::findByName('OrganizationAdmin');

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $admin->id]), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['PeopleCountViewer'],
        ])
        ->assertSessionHasErrors('roles');

    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $adminRole->id,
        'model_id' => $admin->id,
        'model_type' => User::class,
    ]);
});

it('rejects a new organization user with another users phone number', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    User::factory()->create(['phone' => '+41790000000']);

    $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), [
            'name' => 'Duplicate Phone',
            'email' => 'new@example.com',
            'phone' => '+41 79 000 00 00',
        ])
        ->assertSessionHasErrors('phone');
});

it('can update a user for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create(['phone' => '+41790000000']);
    $org->users()->attach($user->id);
    $newName = 'Updated Name';

    $response = $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $newName,
            'email' => $user->email,
        ]);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $newName, 'phone' => '+41790000000']);
});

it('rejects duplicate formatted phone when updating an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    User::factory()->create(['phone' => '+41790000000']);
    $user = User::factory()->create();
    $org->users()->attach($user->id);

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+41 79 000 00 00',
        ])
        ->assertSessionHasErrors('phone');
});

it('can delete a user for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'delete-me@example.com']);
    $org->users()->attach($user->id);

    $response = $this->actingAs($admin)
        ->delete(route('orgmgmt.users.destroy', ['organization' => $org->slug, 'user' => $user->id]));
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseMissing('users', ['id' => $user->id, 'email' => 'delete-me@example.com']);
});

it('detaches an organization user who belongs to another organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach([$org->id, $otherOrg->id]);

    $response = $this->actingAs($admin)
        ->delete(route('orgmgmt.users.destroy', ['organization' => $org->slug, 'user' => $user->id]));

    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('organization_user', ['organization_id' => $org->id, 'user_id' => $user->id]);
    $this->assertDatabaseHas('organization_user', ['organization_id' => $otherOrg->id, 'user_id' => $user->id]);
});

it('removes only current organization access when detaching an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach([$org->id, $otherOrg->id]);
    $viewerRole = Role::findByName('PeopleCountViewer');
    $adminRole = Role::findByName('OrganizationAdmin');
    $sensorPermission = Permission::findOrCreate('peoplecount.sensors.index');
    $eventPermission = Permission::findOrCreate('peoplecount.events.index');

    setPermissionsOrgId($org->id);
    $user->assignRole($viewerRole);
    $user->givePermissionTo($sensorPermission);

    setPermissionsOrgId($otherOrg->id);
    $user->assignRole($adminRole);
    $user->givePermissionTo($eventPermission);

    $this->actingAs($admin)
        ->delete(route('orgmgmt.users.destroy', ['organization' => $org->slug, 'user' => $user->id]))
        ->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));

    $this->assertDatabaseMissing('model_has_roles', [
        'organization_id' => $org->id,
        'role_id' => $viewerRole->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
    $this->assertDatabaseMissing('model_has_permissions', [
        'organization_id' => $org->id,
        'permission_id' => $sensorPermission->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
    $this->assertDatabaseHas('model_has_roles', [
        'organization_id' => $otherOrg->id,
        'role_id' => $adminRole->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
    $this->assertDatabaseHas('model_has_permissions', [
        'organization_id' => $otherOrg->id,
        'permission_id' => $eventPermission->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
});

it('forbids non-admins from accessing orgmgmt users index', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $this->actingAs($user)
        ->get(route('orgmgmt.users.index', ['organization' => $org->slug]))
        ->assertForbidden();
});

it('forbids deleting your own user via orgmgmt', function () {
    $user = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $org->users()->attach($user->id);
    $response = $this->actingAs($user)
        ->delete(route('orgmgmt.users.destroy', ['organization' => $org->slug, 'user' => $user->id]));
    $response->assertForbidden();
});

it('does not expose users from other organizations through orgmgmt show', function () {
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $outsider = User::factory()->create();
    $otherOrg->users()->attach($outsider->id);

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.show', ['organization' => $org->slug, 'user' => $outsider->id]))
        ->assertNotFound();
});

it('does not expose users from other organizations through orgmgmt edit', function () {
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $outsider = User::factory()->create();
    $otherOrg->users()->attach($outsider->id);

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.edit', ['organization' => $org->slug, 'user' => $outsider->id]))
        ->assertNotFound();
});

it('does not update users from other organizations through orgmgmt', function () {
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $outsider = User::factory()->create(['name' => 'Original Name']);
    $otherOrg->users()->attach($outsider->id);

    $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $outsider->id]), [
            'name' => 'Changed Name',
            'email' => $outsider->email,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('users', ['id' => $outsider->id, 'name' => 'Original Name']);
});

it('does not delete users from other organizations through orgmgmt', function () {
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $admin = User::factory()->organizationAdmin($org)->create();
    $outsider = User::factory()->create();
    $otherOrg->users()->attach($outsider->id);

    $this->actingAs($admin)
        ->delete(route('orgmgmt.users.destroy', ['organization' => $org->slug, 'user' => $outsider->id]))
        ->assertNotFound();

    $this->assertDatabaseHas('users', ['id' => $outsider->id]);
});
