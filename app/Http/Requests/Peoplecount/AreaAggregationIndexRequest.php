<?php

namespace App\Http\Requests\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class AreaAggregationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('peoplecount.areas.index');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
