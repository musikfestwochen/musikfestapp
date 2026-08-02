<?php

use App\Http\Requests\Peoplecount\EventCreateRequest;
use App\Models\User;

covers(EventCreateRequest::class);

beforeEach(function () {
    $this->request = new EventCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can create events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
