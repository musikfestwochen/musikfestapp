<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use AllowDynamicProperties;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[AllowDynamicProperties] class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('admin.users.update');
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
            'organization_ids' => ['sometimes', 'array'],
            'organization_ids.*' => ['integer', 'exists:organizations,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! array_key_exists('phone', $this->all())) {
            return;
        }

        $phone = $this->input('phone');

        if (is_string($phone) || $phone === null) {
            $this->merge(['phone' => User::normalizePhone($phone)]);
        }
    }
}
