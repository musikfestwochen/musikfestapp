<?php

use App\Http\Requests\Peoplecount\SensorShareDestroyRequest;
use App\Models\User;

covers(SensorShareDestroyRequest::class);

beforeEach(function () {
    $this->request = new SensorShareDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can update sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
