<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaRecurringResetStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.area_resets.store');
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
                    ->where('timezone', $this->timezone),
            ],
            'timezone' => ['required', 'string', 'timezone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{reset_value: int, reset_time: string, timezone: string, notes?: string|null}
     */
    public function payload(): array
    {
        $payload = [
            'reset_value' => $this->integer('reset_value'),
            'reset_time' => $this->string('reset_time')->toString(),
            'timezone' => $this->string('timezone')->toString(),
        ];

        if ($this->has('notes')) {
            $payload['notes'] = $this->filled('notes') ? $this->string('notes')->toString() : null;
        }

        return $payload;
    }
}
