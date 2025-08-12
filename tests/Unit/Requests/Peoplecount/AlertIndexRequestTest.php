<?php

use App\Http\Requests\Peoplecount\AlertIndexRequest;
use App\Models\User;

covers(AlertIndexRequest::class);

beforeEach(function () {
    $this->request = new AlertIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can index alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
