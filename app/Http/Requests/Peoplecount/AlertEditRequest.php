<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlertEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.alerts.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
