<?php

use App\Http\Requests\StageSafety\SensorShowRequest;
use App\Models\User;

covers(SensorShowRequest::class);

it('has no input rules', function () {
    expect((new SensorShowRequest)->rules())->toBe([]);
});

it('authorizes the sensor show permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.show')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorShowRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor show permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.show')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorShowRequest)->authorize())->toBeFalse();
});
