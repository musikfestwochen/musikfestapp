<?php

use App\Http\Requests\StageSafety\SensorEditRequest;
use App\Models\User;

covers(SensorEditRequest::class);

it('has no input rules', function () {
    expect((new SensorEditRequest)->rules())->toBe([]);
});

it('authorizes the sensor edit permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.edit')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorEditRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor edit permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.edit')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorEditRequest)->authorize())->toBeFalse();
});
