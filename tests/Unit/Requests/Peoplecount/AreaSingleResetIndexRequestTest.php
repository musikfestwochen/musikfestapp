<?php

use App\Http\Requests\Peoplecount\AreaSingleResetIndexRequest;
use App\Models\User;

covers(AreaSingleResetIndexRequest::class);

beforeEach(function () {
    $this->request = new AreaSingleResetIndexRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBeEmpty();
});

it('authorizes when user can index area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
