<?php

use App\Http\Requests\Widgets\Peoplecount\ActiveAreaCountsIndexRequest;
use App\Models\User;

covers(ActiveAreaCountsIndexRequest::class);

beforeEach(function () {
    $this->request = new ActiveAreaCountsIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.active_area_counts')->andReturn(true);

    // Create a partial mock of the request
    $request = Mockery::mock(ActiveAreaCountsIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.active_area_counts')->andReturn(false);

    // Create a partial mock of the request
    $request = Mockery::mock(ActiveAreaCountsIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});

it('does not authorize when user is unauthenticated', function () {
    // Create a partial mock of the request with no authenticated user
    $request = Mockery::mock(ActiveAreaCountsIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn(null);

    expect($request->authorize())->toBeFalse();
});
