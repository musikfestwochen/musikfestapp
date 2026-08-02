<?php

use App\Http\Requests\Peoplecount\EventIndexRequest;
use App\Models\User;

covers(EventIndexRequest::class);

beforeEach(function () {
    $this->request = new EventIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can index events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
