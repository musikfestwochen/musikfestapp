<?php

use App\Http\Requests\Peoplecount\AreaRecurringResetShowRequest;
use App\Models\User;

covers(AreaRecurringResetShowRequest::class);

beforeEach(function () {
    $this->request = new AreaRecurringResetShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can show area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot show area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.show')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
