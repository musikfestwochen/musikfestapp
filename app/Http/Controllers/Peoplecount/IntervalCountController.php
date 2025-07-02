<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\IntervalCountService;
use Illuminate\Http\Request;

class IntervalCountController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {

        /** @var Sensor $sensor */
        $sensor = auth('sanctum')->user();

        app(IntervalCountService::class)
            ->processIntervalCount(
                sensor: $sensor,
                data: $request->all()
            );
    }
}
