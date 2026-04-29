<?php

namespace App\Http\Requests\Orgmgmt;

use App\Models\User;
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
        $userId = $userRoute instanceof User ? $userRoute->id : $userRoute; // @pest-mutate-ignore

        throw_if($userId && $userId === auth()->id(), AuthorizationException::class, 'You cannot delete your own account.');

        return auth()->user()->can('orgmgmt.users.destroy');
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
