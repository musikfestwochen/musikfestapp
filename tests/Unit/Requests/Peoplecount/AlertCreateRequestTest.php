<?php

use App\Http\Requests\Peoplecount\AlertCreateRequest;
use App\Models\User;

covers(AlertCreateRequest::class);

beforeEach(function () {
    $this->request = new AlertCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can create alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
