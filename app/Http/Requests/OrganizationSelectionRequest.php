<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OrganizationSelectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // All authenticated users can select an organization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value != GLOBAL_ORG_ID && ! \App\Models\Organization::query()->where('id', $value)->exists()) {
                        $fail('The selected organization is invalid.');
                    }
                },
            ],
        ];
    }
}
