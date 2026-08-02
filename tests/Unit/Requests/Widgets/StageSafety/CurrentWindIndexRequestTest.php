<?php

use App\Http\Requests\Widgets\StageSafety\CurrentWindIndexRequest;
use App\Models\User;

covers(CurrentWindIndexRequest::class);

it('has no input rules', function () {
    expect((new CurrentWindIndexRequest)->rules())->toBeEmpty();
});

it('authorizes users with the monitoring permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.monitoring.view')->andReturnTrue();
    $request = new CurrentWindIndexRequest;
    $request->setUserResolver(fn (?string $guard = null): User => $user);

    expect($request->authorize())->toBeTrue();
});

it('denies users without the monitoring permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.monitoring.view')->andReturnFalse();
    $request = new CurrentWindIndexRequest;
    $request->setUserResolver(fn (?string $guard = null): User => $user);

    expect($request->authorize())->toBeFalse();
});
