<?php

use App\Http\Requests\StageSafety\SensorArchiveUpdateRequest;
use App\Models\User;

covers(SensorArchiveUpdateRequest::class);

it('has no input rules', function () {
    expect((new SensorArchiveUpdateRequest)->rules())->toBe([]);
});

it('authorizes the sensor update permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.update')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorArchiveUpdateRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor update permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.update')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorArchiveUpdateRequest)->authorize())->toBeFalse();
});
