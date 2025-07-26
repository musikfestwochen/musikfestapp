<?php

use App\Http\Requests\Peoplecount\EventDestroyRequest;
use App\Models\User;

covers(EventDestroyRequest::class);

beforeEach(function () {
    $this->request = new EventDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
