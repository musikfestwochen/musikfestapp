<?php

use App\Http\Requests\Admin\UpdatePeoplecountAggregationRequest;
use App\Models\User;

covers(UpdatePeoplecountAggregationRequest::class);

beforeEach(function () {
    $this->request = new UpdatePeoplecountAggregationRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can update aggregations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.peoplecount_aggregations.update')->andReturn(true);

    $request = Mockery::mock(UpdatePeoplecountAggregationRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot update aggregations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.peoplecount_aggregations.update')->andReturn(false);

    $request = Mockery::mock(UpdatePeoplecountAggregationRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});

it('does not authorize when user is unauthenticated', function () {
    $request = Mockery::mock(UpdatePeoplecountAggregationRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn(null);

    expect($request->authorize())->toBeFalse();
});
