<?php

use App\Http\Requests\Peoplecount\AreaShowRequest;
use App\Models\User;

covers(AreaShowRequest::class);

beforeEach(function () {
    $this->request = new AreaShowRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can show areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
