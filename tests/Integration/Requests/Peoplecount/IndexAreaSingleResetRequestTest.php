<?php

use App\Http\Requests\Peoplecount\IndexAreaSingleResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

covers(IndexAreaSingleResetRequest::class);

beforeEach(function () {
    $this->request = new IndexAreaSingleResetRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can index area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.index')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
