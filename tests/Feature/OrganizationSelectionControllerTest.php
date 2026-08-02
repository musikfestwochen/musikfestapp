<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects to organization dashboard if user has only one organization', function () {
    // Create a user and an organization
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    // Assign the organization to the user
    $user->organizations()->attach($organization->id);

    // Mock the OrganizationSelectionService to return a collection with one organization
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('getOrganizationsForUser')
            ->once()
            ->andReturn(new Collection([$organization]));
    });

    // Act as the user and visit the organization selection page
    $response = $this->actingAs($user)->get('/start');

    // Assert that the user is redirected to the organization dashboard
    $response->assertRedirect('/'.$organization->slug.'/dashboard');
});

it('shows organization selection page if user has multiple organizations', function () {
    // Create a user and organizations
    $user = User::factory()->create();
    $organizations = Organization::factory()->count(2)->create();

    // Assign the organizations to the user
    $user->organizations()->attach($organizations->pluck('id'));

    // Mock the OrganizationSelectionService to return a collection with multiple organizations
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organizations) {
        $mock->shouldReceive('getOrganizationsForUser')
            ->once()
            ->andReturn(new Collection($organizations));
    });

    // Act as the user and visit the organization selection page
    $response = $this->actingAs($user)->get('/start');

    // Assert that the organization selection page is rendered with the organizations
    $response->assertInertia(fn (Assert $page): AssertableJson => $page
        ->component('OrganizationSelection')
        ->has('organizations', $organizations->count())
    );
});

it('redirects to organization dashboard after successful selection', function () {
    // Create a user and an organization
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    // Assign the organization to the user
    $user->organizations()->attach($organization->id);

    // Mock the OrganizationSelectionService to process the organization selection
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('processOrganizationSelection')
            ->once()
            ->with($organization->id)
            ->andReturn($organization->slug);
    });

    // Act as the user and submit the organization selection form
    $response = $this->actingAs($user)->post(route('organization-selection.store'), [
        'organization_id' => $organization->id,
    ]);

    // Assert that the user is redirected to the organization dashboard
    $response->assertRedirect('/'.$organization->slug.'/dashboard');
});

it('redirects back with error if user does not have access to the organization', function () {
    // Create a user and an organization
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    // Mock the OrganizationSelectionService to throw an AuthorizationException
    $this->mock(OrganizationSelectionService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('processOrganizationSelection')
            ->once()
            ->with($organization->id)
            ->andThrow(new AuthorizationException('You do not have access to this organization.'));
    });

    // Act as the user and submit the organization selection form
    $response = $this->actingAs($user)->post(route('organization-selection.store'), [
        'organization_id' => $organization->id,
    ]);

    // Assert that the user is redirected back with an error message
    $response->assertRedirect(route('organization-selection.index'));
    $response->assertSessionHas('error', 'You do not have access to this organization.');
});
