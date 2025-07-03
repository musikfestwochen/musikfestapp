<?php

use App\Http\Controllers\Peoplecount\IntervalCountController;
use App\Models\Peoplecount\Sensor;
use App\Services\Peoplecount\IntervalCountService;
use App\Services\Peoplecount\SensorService;
use Illuminate\Support\Facades\Route;

it('calls IntervalCountService with authenticated sensor and payload', function () {
    Route::post(route('peoplecount.interval-count.store'), [IntervalCountController::class, 'store']);

    $sensor = Sensor::factory()->create();
    $payload = ['some' => 'data'];

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->withArgs(function ($argSensor, $argData) use ($sensor, $payload): bool {
            return $argSensor->id === $sensor->id && $argData === $payload;
        })
        ->andReturn(2); // Return number of processed records

    $this->app->instance(IntervalCountService::class, $mock);

    $token = app(SensorService::class)->createOrRegenerateToken($sensor);

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(201) // 201 Created
        ->assertJson([
            'message' => 'Interval count data processed successfully.',
            'count' => 2,
        ]);
});

it('returns 200 when no records are processed', function () {
    Route::post(route('peoplecount.interval-count.store'), [IntervalCountController::class, 'store']);

    $sensor = Sensor::factory()->create();
    $payload = ['some' => 'data'];

    $mock = Mockery::mock(IntervalCountService::class);
    $mock->shouldReceive('processIntervalCount')
        ->once()
        ->withArgs(function ($argSensor, $argData) use ($sensor, $payload): bool {
            return $argSensor->id === $sensor->id && $argData === $payload;
        })
        ->andReturn(0); // Return 0 processed records

    $this->app->instance(IntervalCountService::class, $mock);

    $token = app(SensorService::class)->createOrRegenerateToken($sensor);

    $this->postJson(route('peoplecount.interval-count.store'), $payload, [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(200) // 200 OK
        ->assertJson([
            'message' => 'No interval count data to process.',
        ]);
});

it('uses the correct form requests', function () {
    // middleware
    test()->assertRouteUsesMiddleware(
        'peoplecount.interval-count.store',
        ['auth:sanctum'],
    );
});
