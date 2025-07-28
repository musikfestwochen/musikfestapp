<?php

namespace App\Http\Requests\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;
use RRule\RRule;

class UpdateAreaRecurringResetRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:peoplecount_events,id'],
            'reset_value' => ['required', 'integer', 'min:0'],
            'rrule' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                try {
                    new RRule($value);
                } catch (\Exception $exception) {
                    $fail('The '.$attribute.' must be a valid RRULE format.');
                }
            }],
            'timezone' => ['required', 'string', 'timezone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
