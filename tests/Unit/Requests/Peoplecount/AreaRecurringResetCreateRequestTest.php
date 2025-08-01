<?php

use App\Http\Requests\Peoplecount\AreaRecurringResetCreateRequest;
use App\Models\User;

covers(AreaRecurringResetCreateRequest::class);

beforeEach(function () {
    $this->request = new AreaRecurringResetCreateRequest;
});

it('has correct rules', function () {
    $expectedRules = [];

    $actualRules = $this->request->rules();

    expect($actualRules)->toBe($expectedRules);
});

it('authorizes when user can create area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot create area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.create')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
