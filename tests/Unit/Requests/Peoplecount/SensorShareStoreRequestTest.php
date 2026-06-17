<?php

use App\Http\Requests\Peoplecount\SensorShareStoreRequest;
use App\Models\User;

covers(SensorShareStoreRequest::class);

beforeEach(function () {
    $this->request = new SensorShareStoreRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'borrower_organization_id' => ['required', 'integer', 'exists:organizations,id'],
        'starts_at' => ['required', 'date', 'before:ends_at'],
        'ends_at' => ['required', 'date', 'after:starts_at'],
    ]);
});

it('authorizes when user can update sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot update sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
