<?php

use App\Http\Controllers\OrganizationSelectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permissions.global_organization', 'auth', 'verified'])->group(function () {

    Route::get('/', [OrganizationSelectionController::class, 'index'])
        ->name('home');
    Route::get('start', [OrganizationSelectionController::class, 'index'])
        ->name('organization-selection.index');
    Route::post('organization/select', [OrganizationSelectionController::class, 'store'])
        ->name('organization-selection.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/orgmgmt.php';
