<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlertCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.alerts.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
