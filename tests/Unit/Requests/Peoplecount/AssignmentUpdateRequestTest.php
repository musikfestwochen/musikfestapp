<?php

use App\Http\Requests\Peoplecount\AssignmentUpdateRequest;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;

covers(AssignmentUpdateRequest::class);

beforeEach(function () {
    $this->request = new AssignmentUpdateRequest;
});

it('has correct rules', function () {
    $rules = $this->request->rules();

    expect($rules)->toHaveKey('event_id')
        ->and($rules)->toHaveKey('area_id')
        ->and($rules)->toHaveKey('sensor_id')
        ->and($rules)->toHaveKey('direction_flipped')
        ->and($rules)->toHaveKey('active_from')
        ->and($rules)->toHaveKey('active_to');

    expect($rules['event_id'])->toBe(['required', 'integer', 'exists:peoplecount_events,id']);
    expect($rules['area_id'])->toBe(['required', 'integer', 'exists:peoplecount_areas,id']);
    expect($rules['sensor_id'])->toBe(['required', 'integer', 'exists:peoplecount_sensors,id']);

    // For the Enum rule, we need to check the class type since we can't directly compare instances
    expect($rules['direction_flipped'])->toBe(['required', 'boolean']);

    expect($rules['active_from'])->toBe(['required', 'date', 'before:active_to']);
    expect($rules['active_to'])->toBe(['required', 'date', 'after:active_from']);
});

it('authorizes when user can update assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
