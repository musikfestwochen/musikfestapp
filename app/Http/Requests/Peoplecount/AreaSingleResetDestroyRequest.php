<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use App\Models\Peoplecount\AreaSingleReset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AreaSingleResetDestroyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var AreaSingleReset $singleReset */
        $singleReset = $this->route('single_reset');
        if (auth()->user()->can('peoplecount.area_resets.destroy')) {
            return true;
        }

        return $singleReset->created_by === auth()->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
