<?php

use App\Http\Requests\Peoplecount\AssignmentStoreRequest;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;

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
        ->and($rules['active_from'])->toBe(['required', 'date', 'before:active_to'])
        ->and($rules['active_to'])->toBe(['required', 'date', 'after:active_from']);

});

it('authorizes when user can store assignments', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.assignments.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
