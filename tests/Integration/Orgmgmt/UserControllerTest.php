<?php

use App\Http\Controllers\Orgmgmt\UserController;
use App\Http\Requests\Orgmgmt\UserCreateRequest;
use App\Http\Requests\Orgmgmt\UserDestroyRequest;
use App\Http\Requests\Orgmgmt\UserEditRequest;
use App\Http\Requests\Orgmgmt\UserIndexRequest;
use App\Http\Requests\Orgmgmt\UserShowRequest;
use App\Http\Requests\Orgmgmt\UserStoreRequest;
use App\Http\Requests\Orgmgmt\UserUpdateRequest;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('can list users for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $users = User::factory()->count(3)->create();
    $org->users()->attach($users->pluck('id'));
    $users = $users->sortBy('name')->values();

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.index', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/Users')
            ->where('organization.id', $org->id)
            ->has('users', 3)
            ->where('users.0.id', $users[0]->id)
            ->where('users.1.id', $users[1]->id)
            ->where('users.2.id', $users[2]->id)
        );
});

it('can create a user for an organization without phone', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $userData = User::factory()->make(['phone' => null])->toArray();
    unset($userData['email_verified_at']); // Remove if not fillable

    $response = $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), $userData);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', [
        'email' => $userData['email'],
        'phone' => null,
    ]);
});

it('can create a user for an organization with phone', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $userData = User::factory()->make(['phone' => '+41 79 123 45 67'])->toArray();
    unset($userData['email_verified_at']); // Remove if not fillable

    $response = $this->actingAs($admin)
        ->post(route('orgmgmt.users.store', ['organization' => $org->slug]), $userData);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', [
        'email' => $userData['email'],
        'phone' => '+41791234567',
    ]);
});

it('can update a user for an organization without phone', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create(['phone' => null]);
    $org->users()->attach($user->id);
    $newName = 'Updated Name';

    $response = $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $newName,
            'email' => $user->email,
        ]);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => $newName,
        'phone' => null,
    ]);
});

it('can update a user for an organization with phone', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);
    $newName = 'Updated Name';
    $newPhone = '+41 79 123 45 67';

    $response = $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $newName,
            'email' => $user->email,
            'phone' => $newPhone,
        ]);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => $newName,
        'phone' => '+41791234567',
    ]);
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

it('shows the create user form for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.create', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/NewUser')
            ->where('organization.id', $org->id)
        );
});

it('redirects show to edit for an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);

    $response = $this->actingAs($admin)
        ->get(route('orgmgmt.users.show', ['organization' => $org->slug, 'user' => $user->id]));
    $response->assertRedirect(route('orgmgmt.users.edit', ['organization' => $org->slug, 'user' => $user->id]));
});

it('shows the edit user form for an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.edit', ['organization' => $org->slug, 'user' => $user->id]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/EditUser')
            ->where('organization.id', $org->id)
            ->where('user.id', $user->id)
        );
});

it('uses the correct form requests', function () {

    // middleware
    test()->assertRouteUsesMiddleware(
        'orgmgmt.users.index',
        ['permissions.organization_slug', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        UserController::class,
        'create',
        UserCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.create',
        UserCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        UserController::class,
        'destroy',
        UserDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.destroy',
        UserDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        UserController::class,
        'edit',
        UserEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.edit',
        UserEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        UserController::class,
        'index',
        UserIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.index',
        UserIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        UserController::class,
        'show',
        UserShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.show',
        UserShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        UserController::class,
        'store',
        UserStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.store',
        UserStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        UserController::class,
        'update',
        UserUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'orgmgmt.users.update',
        UserUpdateRequest::class);

});
