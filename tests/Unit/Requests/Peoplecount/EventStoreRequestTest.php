<?php

use App\Http\Requests\Peoplecount\EventStoreRequest;
use App\Models\User;

covers(EventStoreRequest::class);

beforeEach(function () {
    $this->request = new EventStoreRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'name' => ['required', 'string', 'max:255'],
        'starts_at' => ['required', 'date'],
        'ends_at' => ['required', 'date', 'after:starts_at'],
    ]);
});

it('authorizes when user can store events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
