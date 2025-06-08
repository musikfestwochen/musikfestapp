<?php

use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;

covers(UserIndexRequest::class);

beforeEach(function () {
    $this->request = new UserIndexRequest;
});

it('has correct rules', function () {
    $this->assertExactValidationRules([
        'sort' => ['in:name,email'],
        'order' => ['in:asc,desc'],
    ], $this->request->rules());
});

it('authorizes when user can index users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('users.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    $this->assertTrue($this->request->authorize());
});
