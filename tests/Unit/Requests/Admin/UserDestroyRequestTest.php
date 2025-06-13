<?php

use App\Http\Requests\Admin\UserDestroyRequest;
use App\Models\User;

covers(UserDestroyRequest::class);

beforeEach(function () {
    $this->request = new UserDestroyRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can destroy users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.users.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
