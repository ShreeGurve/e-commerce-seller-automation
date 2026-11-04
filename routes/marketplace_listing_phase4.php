<?php

use App\Http\Controllers\ListingReadinessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:listings.view'])->prefix('listing-readiness')->name('listing-readiness.')->group(function () {
    Route::get('/', [ListingReadinessController::class, 'index'])->name('index');
    Route::get('/export', [ListingReadinessController::class, 'export'])->name('export');
});
