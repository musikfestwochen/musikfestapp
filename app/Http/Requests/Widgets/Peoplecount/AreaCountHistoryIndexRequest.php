<?php

declare(strict_types=1);

namespace App\Http\Requests\Widgets\Peoplecount;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

class AreaCountHistoryIndexRequest extends FormRequest
{
    public const int MAX_RANGE_HOURS = 25;

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
            'from' => ['nullable', 'date_format:Y-m-d\TH:i:s.v\Z', 'required_with:to', 'before_or_equal:to'],
            'to' => ['nullable', 'date_format:Y-m-d\TH:i:s.v\Z', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $now = Date::now();
            $from = $this->input('from') ? Date::parse($this->input('from')) : $now->copy()->subHour();
            $to = $this->input('to') ? Date::parse($this->input('to')) : $now;

            if ($from->diffInSeconds($to, false) > self::MAX_RANGE_HOURS * 3600) {
                $validator->errors()->add('to', 'The time range must not exceed '.self::MAX_RANGE_HOURS.' hours.');
            }
        });
    }
}
