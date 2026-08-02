<?php

use App\Http\Requests\Peoplecount\AreaRecurringResetDestroyRequest;
use App\Models\User;

covers(AreaRecurringResetDestroyRequest::class);

beforeEach(function () {
    $this->request = new AreaRecurringResetDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can destroy area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot destroy area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.destroy')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
