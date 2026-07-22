<?php

use App\Http\Requests\StageSafety\SensorIndexRequest;
use App\Models\User;

covers(SensorIndexRequest::class);

it('validates and exposes the archived filter', function () {
    $request = new SensorIndexRequest;

    expect($request->rules())->toBe(['archived' => ['nullable', 'boolean']])
        ->and($request->showArchived())->toBeFalse();

    $request->merge(['archived' => '1']);

    expect($request->showArchived())->toBeTrue();
});

it('authorizes the sensor index permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.index')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorIndexRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor index permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.index')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorIndexRequest)->authorize())->toBeFalse();
});
