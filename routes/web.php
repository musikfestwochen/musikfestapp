<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['permissions.global_organization', 'auth', 'verified'])->group(function () {

    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('home');

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/app_admin.php';
