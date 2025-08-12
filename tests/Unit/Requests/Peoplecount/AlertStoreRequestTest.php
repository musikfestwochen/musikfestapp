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

    // area_id, channel, cooldown_seconds, occupancy_alert_threshold can be compared directly
    expect($rules['area_id'])->toBe(['required', 'exists:peoplecount_areas,id'])
        ->and($rules['channel'])->toBe(['required', 'in:vonage,email'])
        ->and($rules['cooldown_seconds'])->toBe(['required', 'integer', 'min:0'])
        ->and($rules['occupancy_alert_threshold'])->toBe([
            'required_if:type,'.AlertType::OccupancyAlert->value,
            'prohibited_unless:type,'.AlertType::OccupancyAlert->value,
            'integer',
            'min:0',
        ])
        ->and($rules['recipients'])->toBe(['sometimes', 'array'])
        ->and($rules['recipients.*'])->toBe(['integer', 'exists:users,id'])
        ->and($rules['type'])->toHaveCount(2)
        ->and($rules['type'][0])->toBe('required')
        ->and($rules['type'][1])->toBeInstanceOf(Enum::class);

    // type must contain required and an Enum rule for AlertType
});

it('authorizes when user can store alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.store')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
