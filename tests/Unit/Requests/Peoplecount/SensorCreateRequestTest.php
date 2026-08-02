<?php

use App\Http\Requests\Peoplecount\SensorCreateRequest;
use App\Models\User;

covers(SensorCreateRequest::class);

beforeEach(function () {
    $this->request = new SensorCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can create sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
