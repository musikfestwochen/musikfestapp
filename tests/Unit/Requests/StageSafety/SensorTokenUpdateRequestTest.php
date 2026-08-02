<?php

use App\Http\Requests\StageSafety\SensorTokenUpdateRequest;
use App\Models\User;

covers(SensorTokenUpdateRequest::class);

it('has no input rules', function () {
    expect((new SensorTokenUpdateRequest)->rules())->toBeEmpty();
});

it('authorizes the sensor update permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.update')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorTokenUpdateRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor update permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.update')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorTokenUpdateRequest)->authorize())->toBeFalse();
});
