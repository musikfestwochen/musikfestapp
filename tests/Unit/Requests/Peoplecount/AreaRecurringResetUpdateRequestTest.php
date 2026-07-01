<?php

use App\Http\Requests\Peoplecount\AreaRecurringResetUpdateRequest;
use App\Models\User;
use Illuminate\Validation\Rules\Unique;

covers(AreaRecurringResetUpdateRequest::class);

beforeEach(function () {
    $this->request = new AreaRecurringResetUpdateRequest;
});

it('has correct rules', function () {

    $expectedRules = [
        'reset_value' => ['required', 'integer', 'min:0'],
        'reset_time' => ['required', 'date_format:H:i', 'some_unique_rule'],
        'timezone' => ['required', 'string', 'timezone'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ];

    $actualRules = $this->request->rules();

    // Test the basic structure without the unique rule
    expect($actualRules['reset_value'])->toBe($expectedRules['reset_value']);
    expect($actualRules['timezone'])->toBe($expectedRules['timezone']);
    expect($actualRules['notes'])->toBe($expectedRules['notes']);

    // Test that reset_time has the expected structure
    expect($actualRules['reset_time'])->toHaveCount(3);
    expect($actualRules['reset_time'][0])->toBe('required');
    expect($actualRules['reset_time'][1])->toBe('date_format:H:i');
    expect($actualRules['reset_time'][2])->toBeInstanceOf(Unique::class);
});

it('returns typed payload values', function () {
    $data = [
        'reset_value' => '12',
        'reset_time' => '10:00',
        'timezone' => 'Europe/Berlin',
        'notes' => null,
    ];

    $this->request->merge($data);
    $this->request->setValidator(validator($data, [
        'reset_value' => ['required', 'integer'],
        'reset_time' => ['required', 'date_format:H:i'],
        'timezone' => ['required', 'string'],
        'notes' => ['nullable', 'string'],
    ]));

    expect($this->request->payload())->toBe([
        'reset_value' => 12,
        'reset_time' => '10:00',
        'timezone' => 'Europe/Berlin',
        'notes' => null,
    ]);
});

it('validates valid time formats', function () {
    $validTimes = [
        '08:00',
        '14:30',
        '23:59',
        '00:00',
        '12:15',
    ];

    foreach ($validTimes as $validTime) {
        $request = new AreaRecurringResetUpdateRequest;
        $request->merge(['reset_time' => $validTime]);

        $validator = Validator::make(['reset_time' => $validTime], [
            'reset_time' => ['required', 'date_format:H:i'],
        ]);

        expect($validator->passes())->toBeTrue(sprintf("Valid time '%s' should pass validation", $validTime));
    }
});

it('validates invalid time formats', function () {
    $invalidTimes = [
        'invalid',
        '25:00',
        '12:60',
        '',
        '8:00',
        '12:5',
        'abc:def',
    ];

    foreach ($invalidTimes as $invalidTime) {
        $validator = Validator::make(['reset_time' => $invalidTime], [
            'reset_time' => ['required', 'date_format:H:i'],
        ]);

        expect($validator->fails())->toBeTrue(sprintf("Invalid time '%s' should fail validation", $invalidTime));
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
