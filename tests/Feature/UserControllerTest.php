<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('shows the user index page with paginated users', function () {
    User::factory()->count(12)->create();
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/Users')
            ->has('users.data', 10) // pagination limit
        );
});

it('doesnt show the user index page to non-admin users', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

it('sorts users by the requested field and order', function (string $sort, string $order, int $status) {
    User::factory()->count(3)->create();
    $admin = User::factory()->create();

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

it('fails to create a user with an existing email', function () {
    $admin = User::factory()->create();
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
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), ['email' => 'foo@example.com'])
        ->assertSessionHasErrors('name');
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

it('fails to update without name', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), ['email' => 'new@example.com'])
        ->assertSessionHasErrors('name');
});

it('fails to update without email', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), ['name' => 'New Name'])
        ->assertSessionHasErrors('email');
});

it('fails to update with existing email', function () {
    $admin = User::factory()->create();
    $user1 = User::factory()->create([
        'email' => 'existing@email.test',
    ]);
    $user2 = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user2), ['email' => $user1->email])
        ->assertSessionHasErrors('email');
});

it('deletes a user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create([
        'name' => 'Ursula Peter',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    $response->assertSessionHas('status', 'User Ursula Peter deleted successfully.');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
