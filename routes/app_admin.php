<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('users', UserController::class);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('organizations', OrganizationController::class);
});
