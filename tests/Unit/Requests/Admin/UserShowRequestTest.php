<?php

use App\Http\Requests\Admin\UserShowRequest;
use App\Models\User;

covers(UserShowRequest::class);

beforeEach(function () {
    $this->request = new UserShowRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can show users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('users.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
