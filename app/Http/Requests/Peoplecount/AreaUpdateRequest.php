<?php

declare(strict_types=1);

namespace App\Http\Requests\Peoplecount;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AreaUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('peoplecount.areas.update');
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
            'event_id' => ['required', 'integer', 'exists:peoplecount_events,id'],
        ];
    }

    /**
     * @return array{name: string, event_id: int}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'event_id' => $this->integer('event_id'),
        ];
    }
}
