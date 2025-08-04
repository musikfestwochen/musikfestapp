<?php

use App\Http\Controllers\Peoplecount\AreaAggregationController;
use App\Http\Controllers\Peoplecount\AreaController;
use App\Http\Controllers\Peoplecount\AreaRecurringResetController;
use App\Http\Controllers\Peoplecount\AreaSingleResetController;
use App\Http\Controllers\Peoplecount\AssignmentController;
use App\Http\Controllers\Peoplecount\EventController;
use App\Http\Controllers\Peoplecount\SensorController;
use App\Http\Controllers\Peoplecount\SensorTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permissions.organization_slug'])->group(function () {
    Route::prefix('{organization:slug}/peoplecount')->name('peoplecount.')->group(function () {
        Route::resource(
            'sensors',
            SensorController::class
        )->scoped(['organization' => 'slug'])->names('sensors');

        Route::resource(
            'events',
            EventController::class
        )->scoped(['organization' => 'slug'])->names('events');

        Route::resource(
            'areas',
            AreaController::class
        )->scoped(['organization' => 'slug'])->names('areas');

        // Area Single Reset routes (nested under areas)
        Route::resource(
            'areas.single-resets',
            AreaSingleResetController::class
        )->scoped(['organization' => 'slug'])->names('areas.single-resets')->only(['create', 'store', 'destroy']);

        // Area Recurring Reset routes (nested under areas)
        Route::resource(
            'areas.recurring-resets',
            AreaRecurringResetController::class
        )->scoped(['organization' => 'slug'])->names('areas.recurring-resets')->except(['index']);

        Route::resource(
            'assignments',
            AssignmentController::class
        )->scoped(['organization' => 'slug'])->names('assignments');

        // Regenerate token route
        Route::post('sensors/{sensor}/regenerate-token', [SensorTokenController::class, 'update'])
            ->name('sensors.regenerate-token');

        // Area Aggregation route
        Route::get('area-aggregation', [AreaAggregationController::class, 'index'])
            ->name('area-aggregation.index');
    });
});
