<?php

declare(strict_types=1);

namespace App\Http\Requests\Widgets\StageSafety;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

class HistoryIndexRequest extends FormRequest
{
    public const int MAX_RANGE_HOURS = 24;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('stage-safety.monitoring.view');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d\TH:i:s.v\Z', 'required_with:to', 'before_or_equal:to'],
            'to' => ['nullable', 'date_format:Y-m-d\TH:i:s.v\Z', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$from, $to] = $this->range();

            if ($from->diffInSeconds($to, false) > self::MAX_RANGE_HOURS * 3600) {
                $validator->errors()->add('to', 'The time range must not exceed '.self::MAX_RANGE_HOURS.' hours.');
            }
        }];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $now = Date::now();
        $from = $this->validated('from');
        $to = $this->validated('to');

        return [
            is_string($from) ? Date::parse($from) : $now->copy()->subHour(),
            is_string($to) ? Date::parse($to) : $now,
        ];
    }
}
