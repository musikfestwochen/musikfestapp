<?php

use App\Http\Controllers\Peoplecount\SensorController;
use App\Http\Controllers\Peoplecount\SensorTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permissions.organization_slug'])->group(function () {
    Route::prefix('{organization:slug}/peoplecount')->name('peoplecount.')->group(function () {
        Route::resource(
            'sensors',
            SensorController::class
        )->scoped(['organization' => 'slug'])->names('sensors');
        // Regenerate token route
        Route::post('sensors/{sensor}/regenerate-token', [SensorTokenController::class, 'update'])
            ->name('sensors.regenerate-token');
    });
});
