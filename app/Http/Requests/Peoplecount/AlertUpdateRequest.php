<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use App\Enums\Peoplecount\AlertChannel;
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
            'type' => ['required', new Enum(AlertType::class)],
            'channel' => ['required', new Enum(AlertChannel::class)],
            'cooldown_minutes' => ['required', 'integer', 'min:30'],
            'occupancy_alert_threshold' => ['required_if:type,'.AlertType::OccupancyAlert->value, 'prohibited_unless:type,'.AlertType::OccupancyAlert->value, 'integer', 'min:0'],
            'recipients' => ['sometimes', 'nullable', 'array'],
            'recipients.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = [
            ...$this->validated(),
            'cooldown_minutes' => $this->integer('cooldown_minutes'),
        ];

        if (array_key_exists('occupancy_alert_threshold', $payload) && $payload['occupancy_alert_threshold'] !== null) {
            $payload['occupancy_alert_threshold'] = $this->integer('occupancy_alert_threshold');
        }

        if (array_key_exists('recipients', $payload) && is_array($payload['recipients'])) {
            $payload['recipients'] = array_map('intval', $payload['recipients']);
        }

        return $payload;
    }
}
