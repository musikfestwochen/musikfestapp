<?php

use App\Http\Requests\Peoplecount\SensorShareUpdateRequest;
use App\Models\User;

covers(SensorShareUpdateRequest::class);

beforeEach(function () {
    $this->request = new SensorShareUpdateRequest;
});

it('has correct rules', function () {
    expect($this->request->rules())->toBe([
        'borrower_organization_id' => ['required', 'integer', 'exists:organizations,id'],
        'starts_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'before:ends_at'],
        'ends_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'after:starts_at'],
    ]);
});

it('returns typed payload values', function () {
    $data = [
        'borrower_organization_id' => '42',
        'starts_at' => '2026-01-01 10:00:00',
        'ends_at' => '2026-01-01 11:00:00',
    ];

    $this->request->merge($data);
    $this->request->setValidator(validator($data, [
        'borrower_organization_id' => ['required', 'integer'],
        'starts_at' => ['required', 'date'],
        'ends_at' => ['required', 'date'],
    ]));

    expect($this->request->payload())->toBe([
        'borrower_organization_id' => 42,
        'starts_at' => '2026-01-01 10:00:00',
        'ends_at' => '2026-01-01 11:00:00',
    ]);
});

it('authorizes when user can update sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot update sensors', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.sensors.update')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
