<?php

use App\Http\Requests\Peoplecount\SensorEditRequest;
use App\Models\Peoplecount\Sensor;

covers(SensorEditRequest::class);

beforeEach(function () {
    $this->request = new SensorEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can edit sensors', function () {
    $user = Mockery::mock(Sensor::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
