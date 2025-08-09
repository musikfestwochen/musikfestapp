<?php

use App\Http\Requests\Widgets\Peoplecount\SensorHealthIndexRequest;
use App\Models\User;

covers(SensorHealthIndexRequest::class);

beforeEach(function () {
    $this->request = new SensorHealthIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.sensor_health')->andReturn(true);

    // Create a partial mock of the request
    $request = Mockery::mock(SensorHealthIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.sensor_health')->andReturn(false);

    // Create a partial mock of the request
    $request = Mockery::mock(SensorHealthIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});

it('does not authorize when user is unauthenticated', function () {
    // Create a partial mock of the request with no authenticated user
    $request = Mockery::mock(SensorHealthIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn(null);

    expect($request->authorize())->toBeFalse();
});
