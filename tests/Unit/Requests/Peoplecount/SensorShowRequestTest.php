<?php

use App\Http\Requests\Peoplecount\SensorShowRequest;
use App\Models\User;

covers(SensorShowRequest::class);

beforeEach(function () {
    $this->request = new SensorShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can show sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
