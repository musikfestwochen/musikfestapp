<?php

namespace App\Http\Requests\Peoplecount;

use App\Enums\Peoplecount\Direction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
            'direction' => ['required', new Enum(Direction::class)],
            'active_from' => ['required', 'date', 'before:active_to'],
            'active_to' => ['required', 'date', 'after:active_from'],
        ];
    }
}
