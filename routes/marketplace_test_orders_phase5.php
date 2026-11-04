<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/test-orders/create', [OrderController::class, 'createTest'])->middleware('can:orders.edit')->name('test-orders.create');
    Route::post('/test-orders', [OrderController::class, 'storeTest'])->middleware('can:orders.edit')->name('test-orders.store');
});
