<?php

use App\Http\Requests\Peoplecount\SensorIndexRequest;
use App\Models\User;

covers(SensorIndexRequest::class);

beforeEach(function () {
    $this->request = new SensorIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can index sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
