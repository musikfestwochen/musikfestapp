<?php

declare(strict_types=1);

use App\Http\Controllers\Peoplecount\AlertController;
use App\Http\Controllers\Peoplecount\AreaController;
use App\Http\Controllers\Peoplecount\AreaRecurringResetController;
use App\Http\Controllers\Peoplecount\AreaSingleResetController;
use App\Http\Controllers\Peoplecount\AssignmentController;
use App\Http\Controllers\Peoplecount\EventController;
use App\Http\Controllers\Peoplecount\SensorArchiveController;
use App\Http\Controllers\Peoplecount\SensorController;
use App\Http\Controllers\Peoplecount\SensorShareController;
use App\Http\Controllers\Peoplecount\SensorTokenController;
use App\Http\Controllers\Widgets\PeoplecountActiveAreaCountsWidgetController;
use App\Http\Controllers\Widgets\PeoplecountAreaCountHistoryWidgetController;
use App\Http\Controllers\Widgets\PeoplecountMostActiveSensorsWidgetController;
use App\Http\Controllers\Widgets\PeoplecountSensorHealthStatusWidgetController;
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

        Route::resource(
            'areas.alerts',
            AlertController::class
        )->scoped(['organization' => 'slug'])->names('areas.alerts')->except(['index']);

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

        Route::post('sensors/{sensor}/archive', [SensorArchiveController::class, 'store'])
            ->name('sensors.archive.store');
        Route::delete('sensors/{sensor}/archive', [SensorArchiveController::class, 'destroy'])
            ->name('sensors.archive.destroy');

        Route::post('sensors/{sensor}/shares', [SensorShareController::class, 'store'])
            ->name('sensors.shares.store');
        Route::put('sensors/{sensor}/shares/{share}', [SensorShareController::class, 'update'])
            ->name('sensors.shares.update');
        Route::delete('sensors/{sensor}/shares/{share}', [SensorShareController::class, 'destroy'])
            ->name('sensors.shares.destroy');

        // Area Aggregation route
        Route::get('area-aggregation', [PeoplecountActiveAreaCountsWidgetController::class, 'index'])
            ->name('area-aggregation.index');

        // Sensor Health widget route
        Route::get('sensor-health', [PeoplecountSensorHealthStatusWidgetController::class, 'index'])
            ->name('sensor-health.index');

        // Most Active Sensors widget route
        Route::get('most-active-sensors', [PeoplecountMostActiveSensorsWidgetController::class, 'index'])
            ->name('most-active-sensors.index');

        // Area Count History widget route
        Route::get('area-count-history', [PeoplecountAreaCountHistoryWidgetController::class, 'index'])
            ->name('area-count-history.index');
    });
});
