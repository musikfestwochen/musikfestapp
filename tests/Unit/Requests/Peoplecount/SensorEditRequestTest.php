<?php

use App\Http\Requests\Peoplecount\SensorEditRequest;
use App\Models\User;

covers(SensorEditRequest::class);

beforeEach(function () {
    $this->request = new SensorEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can edit sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
