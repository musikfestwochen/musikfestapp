<?php

namespace App\Http\Requests\Widgets\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class MostActiveSensorsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('peoplecount.widgets.most_active_sensors');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
