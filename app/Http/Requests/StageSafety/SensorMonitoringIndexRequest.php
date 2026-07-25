<?php

declare(strict_types=1);

namespace App\Http\Requests\StageSafety;

use App\Http\Requests\Widgets\StageSafety\WindHistoryIndexRequest;

class SensorMonitoringIndexRequest extends WindHistoryIndexRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('stage-safety.sensors.show');
    }
}
