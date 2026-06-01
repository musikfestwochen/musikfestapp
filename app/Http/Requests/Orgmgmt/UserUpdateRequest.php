<?php

declare(strict_types=1);

namespace App\Http\Requests\Orgmgmt;

use AllowDynamicProperties;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[AllowDynamicProperties] class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('orgmgmt.users.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.($this->route('user')->id ?? '')],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone,'.($this->route('user')->id ?? '')],
        ];
    }
}
