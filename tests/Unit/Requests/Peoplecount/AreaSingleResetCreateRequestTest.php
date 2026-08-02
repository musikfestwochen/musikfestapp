<?php

use App\Http\Requests\Peoplecount\AreaSingleResetCreateRequest;
use App\Models\User;

covers(AreaSingleResetCreateRequest::class);

beforeEach(function () {
    $this->request = new AreaSingleResetCreateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can create area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.create')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
