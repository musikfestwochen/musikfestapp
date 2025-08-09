<?php

use App\Http\Requests\Widgets\Peoplecount\MostActiveSensorsIndexRequest;
use App\Models\User;

covers(MostActiveSensorsIndexRequest::class);

beforeEach(function () {
    $this->request = new MostActiveSensorsIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.most_active_sensors')->andReturn(true);

    $request = Mockery::mock(MostActiveSensorsIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.most_active_sensors')->andReturn(false);

    $request = Mockery::mock(MostActiveSensorsIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});

it('does not authorize when user is unauthenticated', function () {
    $request = Mockery::mock(MostActiveSensorsIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn(null);

    expect($request->authorize())->toBeFalse();
});
