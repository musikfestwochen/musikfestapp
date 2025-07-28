<?php

use App\Http\Requests\Peoplecount\UpdateAreaRecurringResetRequest;
use App\Models\User;

covers(UpdateAreaRecurringResetRequest::class);

beforeEach(function () {
    $this->request = new UpdateAreaRecurringResetRequest;
});

it('has correct rules', function () {

    $expectedRules = [
        'event_id' => ['required', 'integer', 'exists:peoplecount_events,id'],
        'reset_value' => ['required', 'integer', 'min:0'],
        'rrule' => ['required', 'string', 'some_callable_closure'],
        'timezone' => ['required', 'string', 'timezone'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ];

    $actualRules = $this->request->rules();

    // Test the basic structure without the closure
    expect($actualRules['event_id'])->toBe($expectedRules['event_id']);
    expect($actualRules['reset_value'])->toBe($expectedRules['reset_value']);
    expect($actualRules['timezone'])->toBe($expectedRules['timezone']);
    expect($actualRules['notes'])->toBe($expectedRules['notes']);

    // Test that rrule has the expected structure
    expect($actualRules['rrule'])->toHaveCount(3);
    expect($actualRules['rrule'][0])->toBe('required');
    expect($actualRules['rrule'][1])->toBe('string');
    expect($actualRules['rrule'][2])->toBeCallable();
});

it('validates valid RRULE formats', function () {
    $rules = $this->request->rules();
    $rruleValidator = $rules['rrule'][2];

    $validRrules = [
        'FREQ=DAILY;INTERVAL=1',
        'FREQ=WEEKLY;BYDAY=MO',
        'FREQ=DAILY;INTERVAL=1;COUNT=3',
        'FREQ=MONTHLY;BYMONTHDAY=15',
    ];

    foreach ($validRrules as $validRrule) {
        $failCalled = false;
        $fail = function ($message) use (&$failCalled) {
            $failCalled = true;
        };

        $rruleValidator('rrule', $validRrule, $fail);

        expect($failCalled)->toBeFalse(sprintf("Valid RRULE '%s' should not fail validation", $validRrule));
    }
});

it('validates invalid RRULE formats', function () {
    $rules = $this->request->rules();
    $rruleValidator = $rules['rrule'][2];

    $invalidRrules = [
        'INVALID_RRULE',
        'FREQ=INVALID',
        'RANDOM_STRING',
        '',
        'FREQ=DAILY;INVALID_PARAM=1',
    ];

    foreach ($invalidRrules as $invalidRrule) {
        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        $rruleValidator('rrule', $invalidRrule, $fail);

        expect($failCalled)->toBeTrue(sprintf("Invalid RRULE '%s' should fail validation", $invalidRrule));
        expect($failMessage)->toBe('The rrule must be a valid RRULE format.');
    }
});

it('authorizes when user can update area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});

it('does not authorize when user cannot update area resets', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.area_resets.update')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeFalse();
});
