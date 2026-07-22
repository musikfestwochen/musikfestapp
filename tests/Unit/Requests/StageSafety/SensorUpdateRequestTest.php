<?php

use App\Http\Requests\StageSafety\SensorUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

covers(SensorUpdateRequest::class);

it('defines controlled sensor and scoped identity rules', function () {
    $organization = new Organization;
    $organization->id = 123;
    $sensor = new Sensor;
    $sensor->id = 456;
    $request = new SensorUpdateRequest;
    $request->merge(['manufacturer' => 'broadweigh']);
    $request->setRouteResolver(fn (): object => new class($organization, $sensor)
    {
        public function __construct(
            private readonly Organization $organization,
            private readonly Sensor $sensor,
        ) {}

        public function parameter(string $name, mixed $default = null): mixed
        {
            return match ($name) {
                'organization' => $this->organization,
                'stageSafetySensor' => $this->sensor,
                default => $default,
            };
        }
    });

    $rules = $request->rules();

    expect($rules['manufacturer'])->toHaveCount(3)
        ->and($rules['manufacturer'][2])->toBeInstanceOf(In::class)
        ->and($rules['model'][2])->toBeInstanceOf(Enum::class)
        ->and($rules['serial'][3])->toBeInstanceOf(Unique::class)
        ->and($rules['name'])->toBe(['nullable', 'string', 'max:255'])
        ->and($rules['location'])->toBe(['nullable', 'string', 'max:255'])
        ->and($rules['stale_after_seconds'])->toBe(['required', 'integer', 'min:1', 'max:86400'])
        ->and($request->after())->toHaveCount(1);
});

it('authorizes the sensor update permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.update')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorUpdateRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor update permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.update')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorUpdateRequest)->authorize())->toBeFalse();
});
