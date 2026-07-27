<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

it('renders module dashboards for module viewers', function (string $role, string $routeName, string $component) {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $viewer->organizations()->attach($organization);
    setPermissionsOrgId($organization->id);
    $viewer->assignRole($role);

    $this->actingAs($viewer)
        ->get(route($routeName, ['organization' => $organization]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('organization.id', $organization->id)
            ->where('organization.slug', $organization->slug)
        );
})->with([
    'Peoplecount' => ['PeopleCountViewer', 'peoplecount.dashboard', 'peoplecount/Dashboard'],
    'Stage Safety' => ['StageSafetyViewer', 'stage-safety.dashboard', 'stage-safety/Dashboard'],
]);

it('forbids module dashboards without module permission', function (string $routeName) {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach($organization);

    $this->actingAs($user)
        ->get(route($routeName, ['organization' => $organization]))
        ->assertForbidden();
})->with([
    'Peoplecount' => 'peoplecount.dashboard',
    'Stage Safety' => 'stage-safety.dashboard',
]);
