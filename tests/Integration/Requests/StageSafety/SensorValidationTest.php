<?php

use App\Enums\StageSafety\SensorType;
use App\Http\Requests\StageSafety\SensorStoreRequest;
use App\Http\Requests\StageSafety\SensorUpdateRequest;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

function validateStageSafetySensorRequest(
    FormRequest $request,
    Organization $organization,
    array $data,
    ?Sensor $sensor = null,
): Validator {
    $request->merge($data);
    $request->setRouteResolver(fn (): object => new class($organization, $sensor)
    {
        public function __construct(
            private readonly Organization $organization,
            private readonly ?Sensor $sensor,
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

    $validator = ValidatorFacade::make($data, $request->rules());

    if (method_exists($request, 'after')) {
        $validator->after($request->after());
    }

    return $validator;
}

function validStageSafetySensorData(array $overrides = []): array
{
    return array_merge([
        'manufacturer' => SensorType::BroadweighBwWss->manufacturer(),
        'model' => SensorType::BroadweighBwWss->model(),
        'identifier' => 'FF1234',
        'name' => null,
        'location' => null,
        'stale_after_seconds' => 300,
    ], $overrides);
}

it('accepts the supported BW-WSS sensor payload', function () {
    $validator = validateStageSafetySensorRequest(
        new SensorStoreRequest,
        Organization::factory()->create(),
        validStageSafetySensorData(),
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects unsupported manufacturer and model values', function (array $overrides, string $field) {
    $validator = validateStageSafetySensorRequest(
        new SensorStoreRequest,
        Organization::factory()->create(),
        validStageSafetySensorData($overrides),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has($field))->toBeTrue();
})->with([
    'manufacturer' => [['manufacturer' => 'unsupported'], 'manufacturer'],
    'model' => [['model' => 'unsupported'], 'model'],
]);

it('enforces sensor identity uniqueness within an organization', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $data = validStageSafetySensorData([
        'manufacturer' => $sensor->manufacturer,
        'model' => $sensor->model,
        'identifier' => $sensor->identifier,
    ]);

    $duplicate = validateStageSafetySensorRequest(new SensorStoreRequest, $organization, $data);
    $otherOrganization = validateStageSafetySensorRequest(
        new SensorStoreRequest,
        Organization::factory()->create(),
        $data,
    );

    expect($duplicate->fails())->toBeTrue()
        ->and($duplicate->errors()->has('identifier'))->toBeTrue()
        ->and($otherOrganization->passes())->toBeTrue();
});

it('allows an existing sensor to retain its identity during update', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();

    $validator = validateStageSafetySensorRequest(
        new SensorUpdateRequest,
        $organization,
        validStageSafetySensorData([
            'manufacturer' => $sensor->manufacturer,
            'model' => $sensor->model,
            'identifier' => $sensor->identifier,
        ]),
        $sensor,
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects an invalid manufacturer and model combination during update', function () {
    $organization = Organization::factory()->create();
    $sensor = Sensor::factory()->for($organization)->create();
    $validator = validateStageSafetySensorRequest(
        new SensorUpdateRequest,
        $organization,
        validStageSafetySensorData(['manufacturer' => 'unsupported']),
        $sensor,
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('model'))->toContain('The selected manufacturer and model combination is invalid.');
});

it('requires an uppercase six-character hexadecimal device ID', function (string $deviceId) {
    $validator = validateStageSafetySensorRequest(
        new SensorStoreRequest,
        Organization::factory()->create(),
        validStageSafetySensorData(['identifier' => $deviceId]),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('identifier'))->toBeTrue();
})->with(['12345', '1234567', 'ff1234', 'FG1234', '12-ABC']);

it('rejects stale thresholds outside the supported range', function (int $seconds) {
    $validator = validateStageSafetySensorRequest(
        new SensorStoreRequest,
        Organization::factory()->create(),
        validStageSafetySensorData(['stale_after_seconds' => $seconds]),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('stale_after_seconds'))->toBeTrue();
})->with([0, 86401]);
