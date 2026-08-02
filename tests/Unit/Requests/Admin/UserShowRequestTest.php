<?php

use App\Http\Requests\Admin\UserShowRequest;
use App\Models\User;

covers(UserShowRequest::class);

beforeEach(function () {
    $this->request = new UserShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can show users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.users.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
