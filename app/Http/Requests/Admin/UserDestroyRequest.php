<?php

namespace App\Http\Requests\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserDestroyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $userRoute = $this->route('user');
        if (is_object($userRoute) && property_exists($userRoute, 'id')) {
            // Prevent the user from deleting themselves
            throw_if($userRoute->id === auth()->id(), new AuthorizationException('You cannot delete your own account.'));
        }

        return auth()->user()->can('admin.users.destroy');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // No specific validation rules needed for deleting a user
        ];
    }
}
