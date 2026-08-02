<?php

use App\Http\Requests\Peoplecount\AlertShowRequest;
use App\Models\User;

covers(AlertShowRequest::class);

beforeEach(function () {
    $this->request = new AlertShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can show alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
