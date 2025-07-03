<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\IntervalCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntervalCountController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {

        /** @var Sensor $sensor */
        $sensor = auth('sanctum')->user();

        $numPersisted = app(IntervalCountService::class)
            ->processIntervalCount(
                sensor: $sensor,
                data: $request->all()
            );

        // Return the number of persisted IntervalCount records
        if ($numPersisted > 0) {
            return response()->json(['message' => 'Interval count data processed successfully.', 'count' => $numPersisted], 201);
        } else {
            return response()->json(['message' => 'No interval count data to process.'], 200);
        }
    }
}
