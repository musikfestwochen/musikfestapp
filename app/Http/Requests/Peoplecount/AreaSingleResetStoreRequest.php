<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AreaSingleResetStoreRequest extends FormRequest
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
            'effective_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{reset_value: int, effective_at: string, notes?: string|null}
     */
    public function payload(): array
    {
        $payload = [
            'reset_value' => $this->integer('reset_value'),
            'effective_at' => $this->string('effective_at')->toString(),
        ];

        if ($this->has('notes')) {
            $payload['notes'] = $this->filled('notes') ? $this->string('notes')->toString() : null;
        }

        return $payload;
    }
}
