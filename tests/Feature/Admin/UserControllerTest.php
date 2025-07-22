<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Requests\Admin\UserCreateRequest;
use App\Http\Requests\Admin\UserDestroyRequest;
use App\Http\Requests\Admin\UserEditRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserShowRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('shows the user index page with all users', function () {
    User::factory()->count(12)->create();
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('admin/Users')
            ->has('users', 13) // pagination limit
        );
});

it('doesnt show the user index page to non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('shows the create user page', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('admin/NewUser')
        );
});

it('creates a user with a random password', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
});

it('creates a user without phone', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'phone' => null,
    ]);
});

it('creates a user with phone', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane2@example.com',
        'phone' => '+41 79 123 45 67',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'jane2@example.com',
        'phone' => '+41791234567',
    ]);
});

it('fails to create user with invalid email', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Invalid Email User',
        'email' => 'invalid-email',
    ]);

    $response->assertSessionHasErrors('email');
});

it('fails to create a user with an existing email', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create([
        'email' => 'existing@email.test',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Duplicate Email User',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('email');
});

it('fails to store user without name', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), ['email' => 'foo@example.com'])
        ->assertSessionHasErrors('name');
});

it('redirects to the edit page when trying to show a user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);
    $response = $this->get(route('admin.users.show', $user));

    $response->assertRedirect(route('admin.users.edit', $user));
});

it('shows the edit user page', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $user))
        ->assertInertia(fn (AssertableInertia $page): AssertableJson => $page->component('admin/EditUser')
            ->where('user.id', $user->id)
        );
});

it('updates a user successfully', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

it('updates a user without phone', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create(['phone' => null]);

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => null,
    ]);
});

it('updates a user with phone', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'email' => 'updated2@example.com',
        'phone' => '+41 79 123 45 67',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated2@example.com',
        'phone' => '+41791234567',
    ]);
});

it('fails to update without name', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), ['email' => 'new@example.com'])
        ->assertSessionHasErrors('name');
});

it('fails to update without email', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), ['name' => 'New Name'])
        ->assertSessionHasErrors('email');
});

it('fails to update with existing email', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user1 = User::factory()->create([
        'email' => 'existing@email.test',
    ]);
    $user2 = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user2), ['name' => $user2->email, 'email' => $user1->email])
        ->assertSessionHasErrors('email');
});

it('deletes a user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create([
        'name' => 'Ursula Peter',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $response->assertSessionHas('status', 'User Ursula Peter deleted successfully.');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('can attach an organization to a user', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $user->organizations()->attach($org);

    expect($user->organizations->contains($org))->toBeTrue();
});

it('uses the correct form requests', function () {

    // middleware
    test()->assertRouteUsesMiddleware(
        'admin.users.index',
        ['permissions.global_organization', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        UserController::class,
        'create',
        UserCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.create',
        UserCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        UserController::class,
        'destroy',
        UserDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.destroy',
        UserDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        UserController::class,
        'edit',
        UserEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.edit',
        UserEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        UserController::class,
        'index',
        UserIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.index',
        UserIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        UserController::class,
        'show',
        UserShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.show',
        UserShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        UserController::class,
        'store',
        UserStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.store',
        UserStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        UserController::class,
        'update',
        UserUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'admin.users.update',
        UserUpdateRequest::class);

});
