<?php

use App\Http\Requests\StageSafety\SensorDestroyRequest;
use App\Models\User;

covers(SensorDestroyRequest::class);

it('has no input rules', function () {
    expect((new SensorDestroyRequest)->rules())->toBeEmpty();
});

it('authorizes the sensor destroy permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.destroy')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorDestroyRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor destroy permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.destroy')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorDestroyRequest)->authorize())->toBeFalse();
});
