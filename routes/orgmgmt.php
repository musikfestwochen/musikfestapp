<?php

declare(strict_types=1);

use App\Http\Controllers\Orgmgmt\UserController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'permissions.organization_slug'])->group(function () {
    Route::get('{organization:slug}', function (Organization $organization) {
        return Inertia::render('orgmgmt/Dashboard', [
            'organization' => $organization,
        ]);
    });

    Route::get('{organization:slug}/dashboard', function (Organization $organization) {
        return Inertia::render('orgmgmt/Dashboard', [
            'organization' => $organization,
        ]);
    })->name('organization.dashboard');

    Route::resource(
        '{organization:slug}/users',
        UserController::class
    )
        ->scoped(['organization' => 'slug'])
        ->names('orgmgmt.users');
});
