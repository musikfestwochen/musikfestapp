<?php

use App\Http\Requests\Peoplecount\AlertDestroyRequest;
use App\Models\User;

covers(AlertDestroyRequest::class);

beforeEach(function () {
    $this->request = new AlertDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
