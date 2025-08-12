<?php

use App\Enums\Peoplecount\AlertType;
use App\Http\Requests\Peoplecount\AlertUpdateRequest;
use App\Models\User;

covers(AlertUpdateRequest::class);

beforeEach(function () {
    $this->request = new AlertUpdateRequest;
});

it('has correct rules', function () {
    $rules = $this->request->rules();

    expect($rules['area_id'])->toBe(['required', 'exists:peoplecount_areas,id']);
    expect($rules['channel'])->toBe(['required', 'in:vonage,email']);
    expect($rules['cooldown_seconds'])->toBe(['required', 'integer', 'min:0']);
    expect($rules['occupancy_alert_threshold'])->toBe([
        'required_if:type,'.AlertType::OccupancyAlert->value,
        'prohibited_unless:type,'.AlertType::OccupancyAlert->value,
        'integer',
        'min:0',
    ]);

    expect($rules['type'])->toHaveCount(2);
    expect($rules['type'][0])->toBe('required');
    expect($rules['type'][1])->toBeInstanceOf(\Illuminate\Validation\Rules\Enum::class);
});

it('authorizes when user can update alerts', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('peoplecount.alerts.update')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect($this->request->authorize())->toBeTrue();
});
