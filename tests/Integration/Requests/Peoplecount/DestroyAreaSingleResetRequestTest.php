<?php

use App\Http\Requests\Peoplecount\DestroyAreaSingleResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

covers(DestroyAreaSingleResetRequest::class);

beforeEach(function () {
    $this->request = new DestroyAreaSingleResetRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can destroy area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.destroy')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
