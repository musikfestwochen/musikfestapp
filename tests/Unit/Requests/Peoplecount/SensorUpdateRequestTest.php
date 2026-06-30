<?php

use App\Http\Requests\Peoplecount\SensorUpdateRequest;
use App\Models\User;

covers(SensorUpdateRequest::class);

beforeEach(function () {
    $this->request = new SensorUpdateRequest;
});

it('authorizes when user can update sensors', function () {
    $user = Mockery::mock(User::class);
    // Updated to match the actual permission string used in the request
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'vendor' => ['required', 'string', 'max:255'],
        'model' => ['required', 'string', 'max:255'],
        'serial' => ['required', 'string', 'max:255'],
        'name' => ['nullable', 'string', 'max:255'],
    ]);
});
