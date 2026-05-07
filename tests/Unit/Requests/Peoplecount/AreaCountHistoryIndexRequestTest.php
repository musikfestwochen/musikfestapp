<?php

use App\Http\Requests\Widgets\Peoplecount\AreaCountHistoryIndexRequest;
use App\Models\User;

covers(AreaCountHistoryIndexRequest::class);

beforeEach(function () {
    $this->request = new AreaCountHistoryIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date'],
    ]);
});

it('authorizes when user can view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.area_count_history')->andReturn(true);

    $request = Mockery::mock(AreaCountHistoryIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeTrue();
});

it('does not authorize when user cannot view widget', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.widgets.area_count_history')->andReturn(false);

    $request = Mockery::mock(AreaCountHistoryIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn($user);

    expect($request->authorize())->toBeFalse();
});

it('does not authorize when user is unauthenticated', function () {
    $request = Mockery::mock(AreaCountHistoryIndexRequest::class)->makePartial();
    $request->shouldReceive('user')->andReturn(null);

    expect($request->authorize())->toBeFalse();
});
