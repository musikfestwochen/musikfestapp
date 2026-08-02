<?php

use App\Http\Requests\Peoplecount\EventEditRequest;
use App\Models\User;

covers(EventEditRequest::class);

beforeEach(function () {
    $this->request = new EventEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can edit events', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.events.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
