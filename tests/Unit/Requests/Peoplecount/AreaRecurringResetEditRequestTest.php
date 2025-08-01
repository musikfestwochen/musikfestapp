<?php

use App\Http\Requests\Peoplecount\AreaRecurringResetEditRequest;
use App\Models\User;

covers(AreaRecurringResetEditRequest::class);

beforeEach(function () {
    $this->request = new AreaRecurringResetEditRequest;
});

it('has correct rules', function () {
    $expectedRules = [];

    $actualRules = $this->request->rules();

    expect($actualRules)->toBe($expectedRules);
});

it('authorizes when user can edit area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot edit area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.edit')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
