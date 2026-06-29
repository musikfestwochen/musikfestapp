<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignmentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.assignments.update');
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
            'direction_flipped' => ['required', 'boolean'],
            'active_from' => ['required', 'date', 'before:active_to'],
            'active_to' => ['required', 'date', 'after:active_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'event_id' => 'integer',
            'area_id' => 'integer',
            'sensor_id' => 'integer',
            'direction_flipped' => 'boolean',
        ];
    }
}
