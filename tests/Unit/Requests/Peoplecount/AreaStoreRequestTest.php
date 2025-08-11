<?php

use App\Http\Requests\Peoplecount\AreaStoreRequest;
use App\Models\User;

covers(AreaStoreRequest::class);

beforeEach(function () {
    $this->request = new AreaStoreRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'name' => ['required', 'string', 'max:255'],
        'event_id' => ['required', 'exists:peoplecount_events,id'],
    ]);
});

it('authorizes when user can store areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
