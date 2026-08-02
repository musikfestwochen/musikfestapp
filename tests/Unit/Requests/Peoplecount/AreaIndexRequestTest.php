<?php

use App\Http\Requests\Peoplecount\AreaIndexRequest;
use App\Models\User;

covers(AreaIndexRequest::class);

beforeEach(function () {
    $this->request = new AreaIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can index areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
