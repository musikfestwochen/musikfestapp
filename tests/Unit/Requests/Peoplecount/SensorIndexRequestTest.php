<?php

use App\Http\Requests\Peoplecount\SensorIndexRequest;
use App\Models\User;

covers(SensorIndexRequest::class);

beforeEach(function () {
    $this->request = new SensorIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'archived' => ['nullable', 'boolean'],
    ]);
});

it('returns typed archived filter value', function () {
    expect($this->request->showArchived())->toBeFalse();

    $this->request->merge(['archived' => '1']);

    expect($this->request->showArchived())->toBeTrue();
});

it('authorizes when user can index sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
