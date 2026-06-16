<?php

use App\Http\Requests\Peoplecount\SensorArchiveUpdateRequest;
use App\Models\User;

covers(SensorArchiveUpdateRequest::class);

beforeEach(function () {
    $this->request = new SensorArchiveUpdateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can update sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
