<?php

use App\Http\Requests\Admin\UserEditRequest;
use App\Models\User;

covers(UserEditRequest::class);

beforeEach(function () {
    $this->request = new UserEditRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules(
        [], $this->request->rules()
    );
});

it('authorizes when user can edit users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.users.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
