<?php

use App\Http\Requests\Peoplecount\EventUpdateRequest;
use App\Models\User;

covers(EventUpdateRequest::class);

beforeEach(function () {
    $this->request = new EventUpdateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'name' => ['required', 'string', 'max:255'],
        'starts_at' => ['required', 'date'],
        'ends_at' => ['required', 'date', 'after:starts_at'],
    ]);
});

it('authorizes when user can update events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
