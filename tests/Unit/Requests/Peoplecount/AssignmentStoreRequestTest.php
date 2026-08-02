<?php

use App\Http\Requests\Peoplecount\AssignmentStoreRequest;
use App\Models\User;

covers(AssignmentStoreRequest::class);

beforeEach(function () {
    $this->request = new AssignmentStoreRequest;
});

it('has correct rules', function () {
    $rules = $this->request->rules();

    expect($rules)->toHaveKey('event_id')
        ->and($rules)->toHaveKey('area_id')
        ->and($rules)->toHaveKey('sensor_id')
        ->and($rules)->toHaveKey('label')
        ->and($rules)->toHaveKey('direction_flipped')
        ->and($rules)->toHaveKey('active_from')
        ->and($rules)->toHaveKey('active_to')
        ->and($rules['event_id'])->toBe(['required', 'integer', 'exists:peoplecount_events,id'])
        ->and($rules['area_id'])->toBe(['required', 'integer', 'exists:peoplecount_areas,id'])
        ->and($rules['sensor_id'])->toBe(['required', 'integer', 'exists:peoplecount_sensors,id'])
        ->and($rules['label'])->toBe(['nullable', 'string', 'max:255'])
        ->and($rules['direction_flipped'])->toBe(['required', 'boolean'])
        ->and($rules['active_from'])->toBe(['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'before:active_to'])
        ->and($rules['active_to'])->toBe(['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'after:active_from']);

});

it('returns typed payload values', function () {
    $data = [
        'event_id' => '1',
        'area_id' => '2',
        'sensor_id' => '3',
        'label' => 'Gate A',
        'direction_flipped' => '1',
        'active_from' => '2026-01-01 10:00:00',
        'active_to' => '2026-01-01 11:00:00',
    ];

    $this->request->merge($data);
    $this->request->setValidator(validator($data, [
        'event_id' => ['required', 'integer'],
        'area_id' => ['required', 'integer'],
        'sensor_id' => ['required', 'integer'],
        'label' => ['nullable', 'string'],
        'direction_flipped' => ['required', 'boolean'],
        'active_from' => ['required', 'date'],
        'active_to' => ['required', 'date'],
    ]));

    expect($this->request->payload())->toBe([
        'event_id' => 1,
        'area_id' => 2,
        'sensor_id' => 3,
        'label' => 'Gate A',
        'direction_flipped' => true,
        'active_from' => '2026-01-01 10:00:00',
        'active_to' => '2026-01-01 11:00:00',
    ]);
});

it('authorizes when user can store assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
