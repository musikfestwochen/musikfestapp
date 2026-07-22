<?php

declare(strict_types=1);

namespace App\Http\Requests\StageSafety;

use App\Enums\StageSafety\SensorType;
use App\Models\Organization;
use App\Models\StageSafety\Sensor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

class SensorUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('stage-safety.sensors.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $sensor = $this->route('stageSafetySensor');

        return [
            'manufacturer' => ['required', 'string', $this->manufacturerRule()],
            'model' => ['required', 'string', Rule::enum(SensorType::class)],
            'serial' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stage_safety_sensors', 'serial')
                    ->where('organization_id', $organization instanceof Organization ? $organization->id : null)
                    ->where('manufacturer', $this->input('manufacturer'))
                    ->ignore($sensor instanceof Sensor ? $sensor->id : null),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'stale_after_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $manufacturer = $this->input('manufacturer');
            $model = $this->input('model');

            if (is_string($manufacturer) && is_string($model) && ! SensorType::fromPair($manufacturer, $model) instanceof SensorType) {
                $validator->errors()->add('model', 'The selected manufacturer and model combination is invalid.');
            }
        }];
    }

    protected function manufacturerRule(): In
    {
        return Rule::in(array_unique(array_map(
            fn (SensorType $sensorType): string => $sensorType->manufacturer(),
            SensorType::cases(),
        )));
    }
}
