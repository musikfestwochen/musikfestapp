<?php

use App\Http\Requests\Peoplecount\StoreAreaSingleResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

covers(StoreAreaSingleResetRequest::class);

beforeEach(function () {
    $this->request = new StoreAreaSingleResetRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'reset_value' => ['required', 'integer', 'min:0'],
        'effective_at' => ['required', 'date'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ]);
});

it('authorizes when user can store area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
