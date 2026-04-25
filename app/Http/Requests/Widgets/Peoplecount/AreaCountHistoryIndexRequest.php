<?php

namespace App\Http\Requests\Widgets\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class AreaCountHistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('peoplecount.widgets.area_count_history');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
