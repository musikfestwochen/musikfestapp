<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'permissions.organization_slug'])->group(function () {
    Route::get('{organization:slug}', function (Organization $organization) {
        return Inertia::render('Dashboard', [
            'organization' => $organization,
        ]);
    });
    Route::get('{organization:slug}/dashboard', function (Organization $organization) {
        return Inertia::render('Dashboard', [
            'organization' => $organization,
        ]);
    })->name('organization.dashboard');
});
