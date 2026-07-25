<?php

declare(strict_types=1);

namespace App\Http\Requests\StageSafety;

use App\Enums\StageSafety\ReadingKind;
use App\Models\StageSafety\Sensor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReadingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sensor = $this->user('sanctum');

        return $sensor instanceof Sensor
            && $sensor->archived_at === null
            && $sensor->tokenCan('*');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', Rule::in([1])],
            'sensor_identifier' => ['required', 'string', 'regex:/\A[0-9A-F]{6}\z/'],
            'observed_at' => [
                'required',
                'string',
                'date',
                'regex:/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+\-]\d{2}:\d{2})\z/',
            ],
            'payload' => ['required', 'array'],
            'payload.kind' => ['required', 'string', Rule::in([
                ReadingKind::WindAverage->value,
                ReadingKind::WindGust->value,
            ])],
            'payload.value' => ['required', 'numeric:strict', 'min:0'],
            'payload.unit' => ['required', 'string', Rule::in(['m/s'])],
            'payload.window_seconds' => ['required', 'integer', 'min:0'],
            'payload.battery_low' => ['required', 'boolean:strict'],
            'payload.rssi_dbm' => ['sometimes', 'integer'],
            'payload.cv' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
