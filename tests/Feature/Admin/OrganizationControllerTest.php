<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Requests\Admin\OrganizationCreateRequest;
use App\Http\Requests\Admin\OrganizationDestroyRequest;
use App\Http\Requests\Admin\OrganizationEditRequest;
use App\Http\Requests\Admin\OrganizationIndexRequest;
use App\Http\Requests\Admin\OrganizationShowRequest;
use App\Http\Requests\Admin\OrganizationStoreRequest;
use App\Http\Requests\Admin\OrganizationUpdateRequest;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('shows the organization index page with paginated organizations', function () {

    Organization::factory()->count(12)->create();
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('organizations.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('admin/Organizations')
            ->has('organizations.data', 10) // pagination limit
        );
});

it('doesnt show the organization index page to non-admin users', function () {
    $this->get(route('organizations.index'))
        ->assertRedirect(route('login'));
});

it('sorts organizations by the requested field and order', function (string $sort, string $order, int $status) {
    Organization::factory()->count(3)->create();
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('organizations.index', ['sort' => $sort, 'order' => $order]))
        ->assertStatus($status);
})->with([
    ['name', 'asc', 200],
    ['email', 'desc', 200],
    ['website', 'asc', 200],
    ['created_at', 'asc', 302],
    ['invalid', 'asc', 302],
]);

it('shows the create organization page', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->get(route('organizations.create'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('admin/NewOrganizationPage')
        );
});

it('creates an organization successfully', function () {
    $admin = User::factory()->globalAdmin()->create();

    $response = $this->actingAs($admin)->post(route('organizations.store'), [
        'name' => 'Test Organization',
        'slug' => 'test-organization',
        'description' => 'Test description',
        'email' => 'org@example.com',
        'phone' => '123456789',
        'website' => 'https://example.com',
    ]);

    $response->assertRedirect(route('organizations.index'));
    $this->assertDatabaseHas('organizations', ['name' => 'Test Organization']);
});

it('fails to create organization without name', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->post(route('organizations.store'), [
            'slug' => 'test-slug',
            'email' => 'org@example.com',
        ])
        ->assertSessionHasErrors('name');
});

it('fails to create organization without slug', function () {
    $admin = User::factory()->globalAdmin()->create();

    $this->actingAs($admin)
        ->post(route('organizations.store'), [
            'name' => 'Test Organization',
            'email' => 'org@example.com',
        ])
        ->assertSessionHasErrors('slug');
});

it('fails to create organization with duplicate name', function () {
    $admin = User::factory()->globalAdmin()->create();
    Organization::factory()->create(['name' => 'Existing Organization']);

    $this->actingAs($admin)
        ->post(route('organizations.store'), [
            'name' => 'Existing Organization',
            'slug' => 'new-slug',
        ])
        ->assertSessionHasErrors('name');
});

it('fails to create organization with duplicate slug', function () {
    $admin = User::factory()->globalAdmin()->create();
    Organization::factory()->create(['slug' => 'existing-slug']);

    $this->actingAs($admin)
        ->post(route('organizations.store'), [
            'name' => 'New Organization',
            'slug' => 'existing-slug',
        ])
        ->assertSessionHasErrors('slug');
});

it('redirects to the edit page when trying to show an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($admin);
    $response = $this->get(route('organizations.show', $organization));

    $response->assertRedirect(route('organizations.edit', $organization));
});

it('shows the edit organization page', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)
        ->get(route('organizations.edit', $organization))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('admin/EditOrganizationPage')
            ->where('organization.id', $organization->id)
        );
});

it('updates an organization successfully', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)->put(route('organizations.update', $organization), [
        'name' => 'Updated Organization',
        'slug' => 'updated-organization',
        'description' => 'Updated description',
        'email' => 'updated@example.com',
        'phone' => '987654321',
        'website' => 'https://updated-example.com',
    ]);

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Updated Organization',
        'slug' => 'updated-organization',
        'description' => 'Updated description',
        'email' => 'updated@example.com',
        'phone' => '987654321',
        'website' => 'https://updated-example.com',
    ]);
});

it('fails to update without name', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)
        ->put(route('organizations.update', $organization), [
            'slug' => 'updated-slug',
            'email' => 'updated@example.com',
        ])
        ->assertSessionHasErrors('name');
});

it('fails to update without slug', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)
        ->put(route('organizations.update', $organization), [
            'name' => 'Updated Organization',
            'email' => 'updated@example.com',
        ])
        ->assertSessionHasErrors('slug');
});

it('fails to update with existing name', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization1 = Organization::factory()->create(['name' => 'Existing Organization']);
    $organization2 = Organization::factory()->create();

    $this->actingAs($admin)
        ->put(route('organizations.update', $organization2), [
            'name' => 'Existing Organization',
            'slug' => 'updated-slug',
        ])
        ->assertSessionHasErrors('name');
});

it('fails to update with existing slug', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization1 = Organization::factory()->create(['slug' => 'existing-slug']);
    $organization2 = Organization::factory()->create();

    $this->actingAs($admin)
        ->put(route('organizations.update', $organization2), [
            'name' => 'Updated Organization',
            'slug' => 'existing-slug',
        ])
        ->assertSessionHasErrors('slug');
});

it('deletes an organization', function () {
    $admin = User::factory()->globalAdmin()->create();
    $organization = Organization::factory()->create([
        'name' => 'Organization To Delete',
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('organizations.destroy', $organization))
        ->assertRedirect(route('organizations.index'));

    $response->assertSessionHas('status', 'Organization Organization To Delete deleted successfully.');
    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});

it('can attach a user to an organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->users()->attach($user);

    expect($org->users->contains($user))->toBeTrue();
});

it('uses the correct form requests', function () {

    // create
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'create',
        OrganizationCreateRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.create',
        OrganizationCreateRequest::class);

    // destroy
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'destroy',
        OrganizationDestroyRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.destroy',
        OrganizationDestroyRequest::class);

    // edit
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'edit',
        OrganizationEditRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.edit',
        OrganizationEditRequest::class);

    // index
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'index',
        OrganizationIndexRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.index',
        OrganizationIndexRequest::class);

    // show
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'show',
        OrganizationShowRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.show',
        OrganizationShowRequest::class);

    // store
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'store',
        OrganizationStoreRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.store',
        OrganizationStoreRequest::class);

    // update
    test()->assertActionUsesFormRequest(
        OrganizationController::class,
        'update',
        OrganizationUpdateRequest::class);
    test()->assertRouteUsesFormRequest(
        'organizations.update',
        OrganizationUpdateRequest::class);

});
