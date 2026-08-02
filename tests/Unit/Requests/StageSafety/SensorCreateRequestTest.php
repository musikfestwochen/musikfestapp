<?php

use App\Http\Requests\StageSafety\SensorCreateRequest;
use App\Models\User;

covers(SensorCreateRequest::class);

it('has no input rules', function () {
    expect((new SensorCreateRequest)->rules())->toBeEmpty();
});

it('authorizes the sensor create permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.create')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorCreateRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor create permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.create')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorCreateRequest)->authorize())->toBeFalse();
});
