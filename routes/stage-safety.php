<?php

declare(strict_types=1);

use App\Http\Controllers\StageSafety\SensorArchiveController;
use App\Http\Controllers\StageSafety\SensorController;
use App\Http\Controllers\StageSafety\SensorTokenController;
use App\Http\Controllers\Widgets\StageSafetyCurrentWindWidgetController;
use App\Http\Controllers\Widgets\StageSafetySensorHealthWidgetController;
use App\Http\Controllers\Widgets\StageSafetyWindHistoryWidgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permissions.organization_slug'])->group(function () {
    Route::scopeBindings()->prefix('{organization:slug}/stage-safety')->name('stage-safety.')->group(function () {
        Route::resource('sensors', SensorController::class)
            ->except('show')
            ->parameters(['sensors' => 'stageSafetySensor'])
            ->names('sensors');

        Route::post('sensors/{stageSafetySensor}/regenerate-token', [SensorTokenController::class, 'update'])
            ->name('sensors.regenerate-token');
        Route::delete('sensors/{stageSafetySensor}/revoke-token', [SensorTokenController::class, 'destroy'])
            ->name('sensors.revoke-token');

        Route::post('sensors/{stageSafetySensor}/archive', [SensorArchiveController::class, 'store'])
            ->name('sensors.archive.store');
        Route::delete('sensors/{stageSafetySensor}/archive', [SensorArchiveController::class, 'destroy'])
            ->name('sensors.archive.destroy');
        Route::get('current-wind', [StageSafetyCurrentWindWidgetController::class, 'index'])
            ->name('current-wind.index');
        Route::get('sensor-health', [StageSafetySensorHealthWidgetController::class, 'index'])
            ->name('sensor-health.index');
        Route::get('wind-history', [StageSafetyWindHistoryWidgetController::class, 'index'])
            ->name('wind-history.index');
    });
});
