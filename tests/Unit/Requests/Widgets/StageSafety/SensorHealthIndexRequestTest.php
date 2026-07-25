<?php

use App\Http\Requests\Widgets\StageSafety\SensorHealthIndexRequest;
use App\Models\User;

covers(SensorHealthIndexRequest::class);

it('has no input rules', function () {
    expect((new SensorHealthIndexRequest)->rules())->toBe([]);
});

it('authorizes users with the monitoring permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.monitoring.view')->andReturnTrue();
    $request = new SensorHealthIndexRequest;
    $request->setUserResolver(fn (?string $guard = null): User => $user);

    expect($request->authorize())->toBeTrue();
});

it('denies users without the monitoring permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.monitoring.view')->andReturnFalse();
    $request = new SensorHealthIndexRequest;
    $request->setUserResolver(fn (?string $guard = null): User => $user);

    expect($request->authorize())->toBeFalse();
});
