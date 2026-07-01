<?php

use App\Http\Requests\Peoplecount\AssignmentUpdateRequest;
use App\Models\User;

covers(AssignmentUpdateRequest::class);

beforeEach(function () {
    $this->request = new AssignmentUpdateRequest;
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
        ->and($rules['active_from'])->toBe(['required', 'date', 'before:active_to'])
        ->and($rules['active_to'])->toBe(['required', 'date', 'after:active_from']);

    // The direction_flipped field uses a boolean validation rule to ensure the value is true or false

});

it('has correct casts', function () {
    expect($this->request->casts())->toBe([
        'event_id' => 'integer',
        'area_id' => 'integer',
        'sensor_id' => 'integer',
        'direction_flipped' => 'boolean',
    ]);
});

it('authorizes when user can update assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
