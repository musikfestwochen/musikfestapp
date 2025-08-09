<?php

namespace App\Http\Requests\Widgets\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class ActiveAreaCountsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('peoplecount.widgets.active_area_counts');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
