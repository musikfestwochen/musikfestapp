<?php

use App\Enums\Peoplecount\AlertType;
use App\Http\Requests\Peoplecount\AlertUpdateRequest;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;

covers(AlertUpdateRequest::class);

beforeEach(function () {
    $this->request = new AlertUpdateRequest;
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

it('returns typed payload values', function () {
    $data = [
        'type' => 'occupancy_alert',
        'channel' => 'email',
        'cooldown_minutes' => '30',
        'occupancy_alert_threshold' => '100',
        'recipients' => ['1', '2'],
    ];

    $this->request->merge($data);
    $this->request->setValidator(validator($data, [
        'type' => ['required', 'string'],
        'channel' => ['required', 'string'],
        'cooldown_minutes' => ['required', 'integer'],
        'occupancy_alert_threshold' => ['required', 'integer'],
        'recipients' => ['nullable', 'array'],
        'recipients.*' => ['integer'],
    ]));

    expect($this->request->payload())->toBe([
        'type' => 'occupancy_alert',
        'channel' => 'email',
        'cooldown_minutes' => 30,
        'occupancy_alert_threshold' => 100,
        'recipients' => [1, 2],
    ]);
});

it('authorizes when user can update alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
