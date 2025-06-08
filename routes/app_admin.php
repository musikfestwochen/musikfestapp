<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permissions.global_organization', 'auth', 'verified'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('organizations', OrganizationController::class);
});
