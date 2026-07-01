<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignmentStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.assignments.store');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:peoplecount_events,id'],
            'area_id' => ['required', 'integer', 'exists:peoplecount_areas,id'],
            'sensor_id' => ['required', 'integer', 'exists:peoplecount_sensors,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'direction_flipped' => ['required', 'boolean'],
            'active_from' => ['required', 'date', 'before:active_to'],
            'active_to' => ['required', 'date', 'after:active_from'],
        ];
    }

    /**
     * @return array{event_id: int, area_id: int, sensor_id: int, label: string|null, direction_flipped: bool, active_from: string, active_to: string}
     */
    public function payload(): array
    {
        return [
            'event_id' => $this->integer('event_id'),
            'area_id' => $this->integer('area_id'),
            'sensor_id' => $this->integer('sensor_id'),
            'label' => $this->filled('label') ? $this->string('label')->toString() : null,
            'direction_flipped' => $this->boolean('direction_flipped'),
            'active_from' => $this->string('active_from')->toString(),
            'active_to' => $this->string('active_to')->toString(),
        ];
    }
}
