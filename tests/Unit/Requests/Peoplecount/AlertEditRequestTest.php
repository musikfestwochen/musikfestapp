<?php

use App\Http\Requests\Peoplecount\AlertEditRequest;
use App\Models\User;

covers(AlertEditRequest::class);

beforeEach(function () {
    $this->request = new AlertEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can edit alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.edit')->andReturn(true);
    $user->shouldReceive('can')->with('orgmgmt.users.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('denies authorization when user cannot edit alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.edit')->andReturn(false);
    $user->shouldReceive('can')->with('orgmgmt.users.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});

it('denies authorization when user cannot view users', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.edit')->andReturn(true);
    $user->shouldReceive('can')->with('orgmgmt.users.index')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
