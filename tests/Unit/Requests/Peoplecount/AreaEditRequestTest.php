<?php

use App\Http\Requests\Peoplecount\AreaEditRequest;
use App\Models\User;

covers(AreaEditRequest::class);

beforeEach(function () {
    $this->request = new AreaEditRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can edit areas', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.areas.edit')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
