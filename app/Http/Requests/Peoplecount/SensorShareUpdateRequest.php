<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SensorShareUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.sensors.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'borrower_organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'before:ends_at'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'after:starts_at'],
        ];
    }

    /**
     * @return array{borrower_organization_id: int, starts_at: string, ends_at: string}
     */
    public function payload(): array
    {
        return [
            'borrower_organization_id' => $this->integer('borrower_organization_id'),
            'starts_at' => $this->string('starts_at')->toString(),
            'ends_at' => $this->string('ends_at')->toString(),
        ];
    }
}
