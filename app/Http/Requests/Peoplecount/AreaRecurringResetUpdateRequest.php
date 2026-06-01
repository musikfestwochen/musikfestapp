<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaRecurringResetUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.area_resets.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reset_value' => ['required', 'integer', 'min:0'],
            'reset_time' => [
                'required',
                'date_format:H:i',
                Rule::unique('peoplecount_area_recurring_resets')
                    ->where('area_id', $this->area_id)
                    ->where('timezone', $this->timezone)
                    ->ignore($this->route('recurring_reset')),
            ],
            'timezone' => ['required', 'string', 'timezone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
