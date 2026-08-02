<?php

use App\Http\Requests\Admin\DestroyPeoplecountAggregationRequest;
use App\Models\User;

covers(DestroyPeoplecountAggregationRequest::class);

beforeEach(function () {
    $this->request = new DestroyPeoplecountAggregationRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can destroy aggregations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.peoplecount_aggregations.destroy')->andReturn(true);

    $request = Mockery::mock(DestroyPeoplecountAggregationRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot destroy aggregations', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('admin.peoplecount_aggregations.destroy')->andReturn(false);

    $request = Mockery::mock(DestroyPeoplecountAggregationRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});

it('does not authorize when user is unauthenticated', function () {
    $request = Mockery::mock(DestroyPeoplecountAggregationRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn(null);

    expect($request->authorize())->toBeFalse();
});
