<?php

use App\Http\Requests\Peoplecount\AreaRecurringResetIndexRequest;
use App\Models\User;

covers(AreaRecurringResetIndexRequest::class);

beforeEach(function () {
    $this->request = new AreaRecurringResetIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can index area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot index area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.index')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
