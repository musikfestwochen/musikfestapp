<?php

use App\Http\Requests\Peoplecount\AreaSingleResetStoreRequest;
use App\Models\User;

covers(AreaSingleResetStoreRequest::class);

beforeEach(function () {
    $this->request = new AreaSingleResetStoreRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'reset_value' => ['required', 'integer', 'min:0'],
        'effective_at' => ['required', 'date'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ]);
});

it('has correct casts', function () {
    expect($this->request->casts())->toBe([
        'reset_value' => 'integer',
    ]);
});

it('authorizes when user can store area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
