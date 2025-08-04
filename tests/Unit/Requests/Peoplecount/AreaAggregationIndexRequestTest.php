<?php

use App\Http\Requests\Peoplecount\AreaAggregationIndexRequest;
use App\Models\User;

covers(AreaAggregationIndexRequest::class);

beforeEach(function () {
    $this->request = new AreaAggregationIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can index areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.index')->andReturn(true);

    // Create a partial mock of the request
    $request = Mockery::mock(AreaAggregationIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot index areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.index')->andReturn(false);

    // Create a partial mock of the request
    $request = Mockery::mock(AreaAggregationIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});
