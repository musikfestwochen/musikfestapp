<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\IntervalCountService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntervalCountController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * TODO: Consider adding request validation class for improved type safety
     * TODO: Consider adding rate limiting for API endpoints
     * TODO: Consider adding request logging for debugging sensor issues
     */
    public function store(Request $request): JsonResponse
    {

        $sensor = auth('sanctum')->user();

        abort_if(! $sensor instanceof Sensor || $sensor->archived_at !== null, 403);

        try {
            $numPersisted = resolve(IntervalCountService::class)
                ->processIntervalCount(
                    sensor: $sensor,
                    data: $request->all()
                );

            // Return the number of persisted IntervalCount records
            if ($numPersisted > 0) {
                return response()->json(['message' => 'Interval count data processed successfully.', 'count' => $numPersisted], 201);
            }

            return response()->json(['message' => 'No interval count data to process.'], 200);
        } catch (Exception $exception) {
            // Return the exception message in JSON response without logging
            return response()->json([
                'error' => 'Processing failed',
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}
