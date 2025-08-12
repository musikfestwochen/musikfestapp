<?php

use App\Http\Requests\Peoplecount\AlertCreateRequest;
use App\Models\User;

covers(AlertCreateRequest::class);

beforeEach(function () {
    $this->request = new AlertCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can create alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.create')->andReturn(true);
    $user->shouldReceive('can')->with('orgmgmt.users.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('denies authorization when user cannot create alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.create')->andReturn(false);
    $user->shouldReceive('can')->with('orgmgmt.users.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});

it('denies authorization when user cannot view users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.create')->andReturn(true);
    $user->shouldReceive('can')->with('orgmgmt.users.index')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
