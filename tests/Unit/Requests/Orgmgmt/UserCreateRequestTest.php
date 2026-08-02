<?php

use App\Http\Requests\Orgmgmt\UserCreateRequest;
use App\Models\User;

covers(UserCreateRequest::class);

beforeEach(function () {
    $this->request = new UserCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can create users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('orgmgmt.users.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
