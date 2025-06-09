<?php

use App\Http\Requests\Admin\UserCreateRequest;
use App\Models\User;

covers(UserCreateRequest::class);

beforeEach(function () {
    $this->request = new UserCreateRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can create users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.users.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
