<?php

namespace App\Http\Requests\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class StoreAreaSingleResetRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reset_value' => ['required', 'integer', 'min:0'],
            'effective_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
