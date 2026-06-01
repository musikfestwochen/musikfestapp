<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AlertStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.alerts.store');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(AlertType::class)],
            'channel' => ['required', new Enum(AlertChannel::class)],
            'cooldown_minutes' => ['required', 'integer', 'min:30'],
            'occupancy_alert_threshold' => ['required_if:type,'.AlertType::OccupancyAlert->value, 'prohibited_unless:type,'.AlertType::OccupancyAlert->value, 'integer', 'min:0'],
            'recipients' => ['sometimes', 'nullable', 'array'],
            'recipients.*' => ['integer', 'exists:users,id'],
        ];
    }
}
