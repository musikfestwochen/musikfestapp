<?php

use App\Http\Requests\Peoplecount\EventShowRequest;
use App\Models\User;

covers(EventShowRequest::class);

beforeEach(function () {
    $this->request = new EventShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can show events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
