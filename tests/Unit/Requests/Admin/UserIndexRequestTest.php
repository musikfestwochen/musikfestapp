<?php

use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;

covers(UserIndexRequest::class);

beforeEach(function () {
    $this->request = new UserIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can index users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.users.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
