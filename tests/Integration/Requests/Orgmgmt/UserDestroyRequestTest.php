<?php

use App\Http\Requests\Orgmgmt\UserDestroyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(UserDestroyRequest::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->request = new UserDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy users', function () {
    $user = Mockery::mock(User::class);
    // Updated to match the actual permission string used in the request
    $user->shouldReceive('can')->with('orgmgmt.users.destroy')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);
    expect($this->request->authorize())->toBeTrue();
});

it('denies authorization to delete themself', function () {
    $user = User::factory()->create();
    $organization = \App\Models\Organization::factory()->create();
    // Attach the organization to the user so $user->organization is not null
    $user->organizations()->attach($organization);
    // Use the first organization for the slug
    $orgSlug = $user->organizations()->first()->slug;

    $response = $this->actingAs($user)->call('DELETE', route('orgmgmt.users.destroy', [
        'organization' => $orgSlug,
        'user' => $user->id,
    ]));
    expect($response->getStatusCode())->toBe(403);
});
