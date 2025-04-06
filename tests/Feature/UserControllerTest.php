<?php

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

covers(UserController::class);

it('shows the user index page with paginated users', function () {
    User::factory()->count(12)->create();
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/Users')
            ->has('users.data', 10) // pagination limit
        );
});

it('shows the create user page', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/NewUserPage')
        );
});

it('creates a user with a random password', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
});

it('fails to create user with invalid email', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Invalid Email User',
        'email' => 'invalid-email',
    ]);

    $response->assertSessionHasErrors('email');
});

it('redirects to the edit page when trying to show a user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);
    $response = $this->get(route('users.show', $user));

    $response->assertRedirect(route('users.edit', $user));
});

it('shows the edit user page', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.edit', $user))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/EditUserPage')
            ->where('user.id', $user->id)
        );
});

it('updates a user successfully', function () {
    $admin = User::factory()->create();
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

it('deletes a user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
