<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('shows the users index page for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $users = User::factory()->count(2)->create();
    $org->users()->attach($users->pluck('id'));

    $this->actingAs($admin)
        ->get(route('orgmgmt.users.index', ['organization' => $org->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('orgmgmt/Users')
            ->where('organization.id', $org->id)
            ->has('users', 2)
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
        );
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

it('redirects show to edit for an organization user', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
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
});

it('can update a user for an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user->id);
    $newName = 'Updated Name';

    $response = $this->actingAs($admin)
        ->put(route('orgmgmt.users.update', ['organization' => $org->slug, 'user' => $user->id]), [
            'name' => $newName,
            'email' => $user->email,
        ]);
    $response->assertRedirect(route('orgmgmt.users.index', ['organization' => $org->slug]));
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $newName]);
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
