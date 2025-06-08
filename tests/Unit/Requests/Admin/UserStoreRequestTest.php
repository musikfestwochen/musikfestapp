<?php

use App\Http\Requests\Admin\UserStoreRequest;
use App\Models\User;

covers(UserStoreRequest::class);

beforeEach(function () {
    $this->request = new UserStoreRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
    ], $this->request->rules());
});

it('authorizes when user can store users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('users.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
