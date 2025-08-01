<?php

use App\Http\Controllers\Peoplecount\IntervalCountController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('peoplecount')->name('peoplecount.')->group(function () {
        Route::post('interval-count', [IntervalCountController::class, 'store'])
            ->name('interval-count.store');
    });
});

Route::post('/webcron', function () {
    Artisan::call('schedule:run');
    Artisan::call('queue:work --stop-when-empty');

    return response()->noContent();
})->middleware('webcron.token');
