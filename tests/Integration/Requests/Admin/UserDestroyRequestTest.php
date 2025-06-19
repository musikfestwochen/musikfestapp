<?php

use App\Http\Requests\Admin\UserDestroyRequest;
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
    $user->shouldReceive('can')->with('admin.users.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('denies authorization to delete themself', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->call('DELETE', route('admin.users.destroy', ['user' => $user->id]));
    expect($response->getStatusCode())->toBe(403);
});
