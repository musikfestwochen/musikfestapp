<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['permissions.global_organization', 'auth', 'verified'])->group(function () {
    Route::get('admin/dashboard', function () {
        return Inertia::render('admin/Dashboard');
    })->name('admin.dashboard');
    Route::resource('admin/users', UserController::class)->names('admin.users');
    Route::resource('admin/organizations', OrganizationController::class)->names('admin.organizations');
});
