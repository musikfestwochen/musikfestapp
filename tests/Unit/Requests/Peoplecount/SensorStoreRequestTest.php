<?php

use App\Http\Requests\Peoplecount\SensorStoreRequest;
use App\Models\User;

covers(SensorStoreRequest::class);

beforeEach(function () {
    $this->request = new SensorStoreRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'vendor' => ['required', 'string', 'max:255'],
        'model' => ['required', 'string', 'max:255'],
        'serial' => ['required', 'string', 'max:255'],
    ]);
});

it('authorizes when user can store sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
