<?php

namespace App\Http\Requests\Peoplecount;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use RRule\RRule;

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
            'rrule' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                try {
                    new RRule($value);
                } catch (Exception $exception) {
                    $fail('The '.$attribute.' must be a valid RRULE format.');
                }
            }],
            'timezone' => ['required', 'string', 'timezone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
