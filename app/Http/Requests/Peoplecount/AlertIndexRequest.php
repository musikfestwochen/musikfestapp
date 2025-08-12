<?php

namespace App\Http\Requests\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class AlertIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.alerts.index');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
