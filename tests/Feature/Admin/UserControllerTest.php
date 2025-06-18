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
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('shows the user index page with paginated users', function () {
    User::factory()->count(12)->create();
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('admin/AdminUsers')
            ->has('users.data', 10) // pagination limit
        );
});

it('doesnt show the user index page to non-admin users', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

it('sorts users by the requested field and order', function (string $sort, string $order, int $status) {
    User::factory()->count(3)->create();
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('users.index', ['sort' => $sort, 'order' => $order]))
        ->assertStatus($status);
})->with([
    ['name', 'asc', 200],
    ['email', 'desc', 200],
    ['created_at', 'asc', 302],
    ['invalid', 'asc', 302],
]);

it('shows the create user page', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertInertia(fn (AssertableInertia $page): \Inertia\Testing\AssertableInertia => $page->component('admin/AdminNewUserPage')
        );
});

it('creates a user with a random password', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
});

it('fails to create user with invalid email', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
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

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Duplicate Email User',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('email');
});

it('fails to store user without name', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), ['email' => 'foo@example.com'])
        ->assertSessionHasErrors('name');
});

it('redirects to the edit page when trying to show a user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);
    $response = $this->get(route('users.show', $user));

    $response->assertRedirect(route('users.edit', $user));
});

it('shows the edit user page', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.edit', $user))
        ->assertInertia(fn (AssertableInertia $page): \Illuminate\Testing\Fluent\AssertableJson => $page->component('admin/AdminEditUserPage')
            ->where('user.id', $user->id)
        );
});

it('updates a user successfully', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

it('fails to update without name', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), ['email' => 'new@example.com'])
        ->assertSessionHasErrors('name');
});

it('fails to update without email', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), ['name' => 'New Name'])
        ->assertSessionHasErrors('email');
});

it('fails to update with existing email', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user1 = User::factory()->create([
        'email' => 'existing@email.test',
    ]);
    $user2 = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user2), ['email' => $user1->email])
        ->assertSessionHasErrors('email');
});

it('deletes a user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $user = User::factory()->create([
        'name' => 'Ursula Peter',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

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
        'users.index',
        ['permissions.global_organization', 'auth', 'verified'],
    );

    // create
    test()->assertActionUsesFormRequest(
        UserController::class,
        'create',
        UserCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.create',
        UserCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        UserController::class,
        'destroy',
        UserDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.destroy',
        UserDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        UserController::class,
        'edit',
        UserEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.edit',
        UserEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        UserController::class,
        'index',
        UserIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.index',
        UserIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        UserController::class,
        'show',
        UserShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.show',
        UserShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        UserController::class,
        'store',
        UserStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.store',
        UserStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        UserController::class,
        'update',
        UserUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'users.update',
        UserUpdateRequest::class);

});
