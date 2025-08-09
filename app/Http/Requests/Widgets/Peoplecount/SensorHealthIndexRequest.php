<?php

namespace App\Http\Requests\Widgets\Peoplecount;

use Illuminate\Foundation\Http\FormRequest;

class SensorHealthIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('peoplecount.widgets.sensor_health');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
