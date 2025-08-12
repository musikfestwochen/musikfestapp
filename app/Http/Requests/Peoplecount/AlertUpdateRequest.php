<?php

namespace App\Http\Requests\Peoplecount;

use App\Enums\Peoplecount\AlertType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AlertUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.alerts.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_id' => ['required', 'exists:peoplecount_areas,id'],
            'type' => ['required', new Enum(AlertType::class)],
            'channel' => ['required', 'in:vonage,email'],
            'cooldown_seconds' => ['required', 'integer', 'min:0'],
            'occupancy_alert_threshold' => ['required_if:type,'.AlertType::OccupancyAlert->value, 'prohibited_unless:type,'.AlertType::OccupancyAlert->value, 'integer', 'min:0'],
            'recipients' => ['sometimes', 'array'],
            'recipients.*' => ['integer', 'exists:users,id'],
        ];
    }
}
