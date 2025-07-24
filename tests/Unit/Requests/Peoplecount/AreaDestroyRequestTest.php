<?php

use App\Http\Requests\Peoplecount\AreaDestroyRequest;
use App\Models\User;

covers(AreaDestroyRequest::class);

beforeEach(function () {
    $this->request = new AreaDestroyRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
