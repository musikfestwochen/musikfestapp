<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('{organization:slug}', function (Organization $organization) {
        // Set the organization context for permissions
        setPermissionsOrgId($organization->id);

        return Inertia::render('Dashboard', [
            'organization' => $organization,
        ]);
    });
    Route::get('{organization:slug}/dashboard', function (Organization $organization) {
        // Set the organization context for permissions
        setPermissionsOrgId($organization->id);

        return Inertia::render('Dashboard', [
            'organization' => $organization,
        ]);
    })->name('organization.dashboard');
});
