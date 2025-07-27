<?php

use App\Http\Requests\Peoplecount\ShowAreaSingleResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

covers(ShowAreaSingleResetRequest::class);

beforeEach(function () {
    $this->request = new ShowAreaSingleResetRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([]);
});

it('authorizes when user can show area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.show')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
