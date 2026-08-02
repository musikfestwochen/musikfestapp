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
        'effective_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ]);
});

it('returns typed payload values', function () {
    $data = [
        'reset_value' => '12',
        'effective_at' => '2026-01-01 10:00:00',
        'notes' => 'Manual correction',
    ];

    $this->request->merge($data);
    $this->request->setValidator(validator($data, [
        'reset_value' => ['required', 'integer'],
        'effective_at' => ['required', 'date'],
        'notes' => ['nullable', 'string'],
    ]));

    expect($this->request->payload())->toBe([
        'reset_value' => 12,
        'effective_at' => '2026-01-01 10:00:00',
        'notes' => 'Manual correction',
    ]);
});

it('authorizes when user can store area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
