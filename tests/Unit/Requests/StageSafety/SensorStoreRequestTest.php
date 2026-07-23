<?php

use App\Http\Requests\StageSafety\SensorStoreRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

covers(SensorStoreRequest::class);

it('defines controlled sensor and scoped identity rules', function () {
    $organization = new Organization;
    $organization->id = 123;
    $request = new SensorStoreRequest;
    $request->merge(['manufacturer' => 'broadweigh']);
    $request->setRouteResolver(fn (): object => new class($organization)
    {
        public function __construct(private readonly Organization $organization) {}

        public function parameter(string $name, mixed $default = null): mixed
        {
            return $name === 'organization' ? $this->organization : $default;
        }
    });

    $rules = $request->rules();

    expect($rules['manufacturer'])->toHaveCount(3)
        ->and($rules['manufacturer'][2])->toBeInstanceOf(In::class)
        ->and($rules['model'][2])->toBeInstanceOf(Enum::class)
        ->and($rules['identifier'][2])->toBe('regex:/\A[0-9A-F]{6}\z/')
        ->and($rules['identifier'][3])->toBeInstanceOf(Unique::class)
        ->and($rules['name'])->toBe(['nullable', 'string', 'max:255'])
        ->and($rules['location'])->toBe(['nullable', 'string', 'max:255'])
        ->and($rules['stale_after_seconds'])->toBe(['required', 'integer', 'min:1', 'max:86400'])
        ->and($request->after())->toHaveCount(1);
});

it('authorizes the sensor store permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.store')->andReturnTrue();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorStoreRequest)->authorize())->toBeTrue();
});

it('denies users without the sensor store permission', function () {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('can')->with('stage-safety.sensors.store')->andReturnFalse();
    Auth::shouldReceive('user')->andReturn($user);

    expect((new SensorStoreRequest)->authorize())->toBeFalse();
});
