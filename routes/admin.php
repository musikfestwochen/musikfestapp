<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PeoplecountAggregationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['permissions.global_organization', 'auth', 'verified'])->group(function () {
    Route::get('admin/dashboard', function () {
        return Inertia::render('admin/Dashboard');
    })->name('admin.dashboard');
    Route::patch('admin/peoplecount-aggregations', [PeoplecountAggregationController::class, 'update'])
        ->name('admin.peoplecount-aggregations.update');
    Route::delete('admin/peoplecount-aggregations', [PeoplecountAggregationController::class, 'destroy'])
        ->name('admin.peoplecount-aggregations.destroy');
    Route::resource('admin/users', UserController::class)->names('admin.users');
    Route::resource('admin/organizations', OrganizationController::class)->names('admin.organizations');
});
