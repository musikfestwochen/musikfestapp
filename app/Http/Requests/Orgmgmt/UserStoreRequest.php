<?php

declare(strict_types=1);

namespace App\Http\Requests\Orgmgmt;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('orgmgmt.users.store');
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', $this->uniquePhoneForEmailUser()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone) || $phone === null) {
            $this->merge(['phone' => User::normalizePhone($phone)]);
        }
    }

    protected function uniquePhoneForEmailUser(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $existingUserId = User::query()
                ->where('email', $this->string('email')->toString())
                ->value('id');

            $duplicatePhone = User::query()->where('phone', $value);

            if ($existingUserId !== null) {
                $duplicatePhone->whereKeyNot($existingUserId);
            }

            if ($duplicatePhone->exists()) {
                $fail('The phone has already been taken.');
            }
        };
    }

    /**
     * @return array{name: string, email: string, phone?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ];
    }
}
