<?php

declare(strict_types=1);

namespace App\Http\Controllers\StageSafety;

use App\Http\Controllers\Controller;
use App\Http\Requests\StageSafety\ReadingStoreRequest;
use App\Models\StageSafety\Sensor;
use App\Services\StageSafety\ReadingService;
use Illuminate\Http\JsonResponse;

class ReadingController extends Controller
{
    public function store(ReadingStoreRequest $request, ReadingService $service): JsonResponse
    {
        $sensor = $request->user('sanctum');

        /** @var Sensor $sensor */
        $service->process($sensor, $request->validated());

        return response()->json([
            'message' => 'Wind readings processed successfully.',
            'count' => 1,
        ]);
    }
}
