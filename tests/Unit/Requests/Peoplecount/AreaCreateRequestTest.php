<?php

use App\Http\Requests\Peoplecount\AreaCreateRequest;
use App\Models\User;

covers(AreaCreateRequest::class);

beforeEach(function () {
    $this->request = new AreaCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can create areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
