<?php

declare(strict_types=1);

namespace App\Http\Requests\Orgmgmt;

use AllowDynamicProperties;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'roles' => ['sometimes', 'array', 'list', 'min:1', $this->notUpdatingOwnRoles()],
            'roles.*' => ['string', 'distinct', Rule::in(['PeopleCountViewer', 'StageSafetyViewer', 'OrganizationAdmin'])],
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

    protected function notUpdatingOwnRoles(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->user()?->is($this->route('user'))) {
                $fail('You cannot change your own roles.');
            }
        };
    }

    /**
     * @return array{name: string, email: string, phone?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (array_key_exists('phone', $validated)) {
            $payload['phone'] = $validated['phone'];
        }

        return $payload;
    }

    /**
     * @return array<int, string>|null
     */
    public function roleNames(): ?array
    {
        $roles = $this->validated('roles');

        if (! is_array($roles)) {
            return null;
        }

        return array_values(array_filter($roles, is_string(...)));
    }
}
