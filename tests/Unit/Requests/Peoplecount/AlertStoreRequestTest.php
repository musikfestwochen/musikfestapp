<?php

use App\Enums\Peoplecount\AlertType;
use App\Http\Requests\Peoplecount\AlertStoreRequest;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;

covers(AlertStoreRequest::class);

beforeEach(function () {
    $this->request = new AlertStoreRequest;
});

it('has correct rules', function () {
    $rules = $this->request->rules();

    expect($rules['cooldown_minutes'])->toBe(['required', 'integer', 'min:30'])
        ->and($rules['occupancy_alert_threshold'])->toBe([
            'required_if:type,'.AlertType::OccupancyAlert->value,
            'prohibited_unless:type,'.AlertType::OccupancyAlert->value,
            'integer',
            'min:0',
        ])
        ->and($rules['recipients'])->toBe(['sometimes', 'nullable', 'array'])
        ->and($rules['recipients.*'])->toBe(['integer', 'exists:users,id'])
        ->and($rules['type'])->toHaveCount(2)
        ->and($rules['type'][0])->toBe('required')
        ->and($rules['type'][1])->toBeInstanceOf(Enum::class)
        ->and($rules['channel'])->toHaveCount(2)
        ->and($rules['channel'][0])->toBe('required')
        ->and($rules['channel'][1])->toBeInstanceOf(Enum::class);
});

it('has correct casts', function () {
    expect($this->request->casts())->toBe([
        'cooldown_minutes' => 'integer',
        'occupancy_alert_threshold' => 'integer',
        'recipients.*' => 'integer',
    ]);
});

it('authorizes when user can store alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
