<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AppearanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User must be authenticated to update own appearance settings
        return auth()->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'eastereggs_activated' => ['required', 'boolean'],
        ];
    }
}
