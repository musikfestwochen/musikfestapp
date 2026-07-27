<?php

use App\Http\Requests\Widgets\StageSafety\HistoryIndexRequest;
use App\Models\User;

covers(HistoryIndexRequest::class);

it('defines paired bounded history inputs', function () {
    $request = new HistoryIndexRequest;

    expect($request->rules())->toBe([
        'from' => ['nullable', 'date', 'required_with:to', 'before_or_equal:to'],
        'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
    ])->and($request->after())->toHaveCount(1)
        ->and(HistoryIndexRequest::MAX_RANGE_HOURS)->toBe(24);
});

it('authorizes users with the monitoring permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.monitoring.view')->andReturnTrue();
    $request = new HistoryIndexRequest;
    $request->setUserResolver(fn (?string $guard = null): User => $user);

    expect($request->authorize())->toBeTrue();
});

it('denies users without the monitoring permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.monitoring.view')->andReturnFalse();
    $request = new HistoryIndexRequest;
    $request->setUserResolver(fn (?string $guard = null): User => $user);

    expect($request->authorize())->toBeFalse();
});
