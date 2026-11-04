<?php

use App\Http\Controllers\FulfilmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/fulfilment-queue', [FulfilmentController::class, 'index'])->middleware('can:orders.view')->name('fulfilment.index');
    Route::put('/orders/{order}/fulfilment', [FulfilmentController::class, 'update'])->middleware('can:orders.edit')->name('fulfilment.update');
    Route::post('/orders/{order}/fulfilment/pack', [FulfilmentController::class, 'pack'])->middleware('can:orders.edit')->name('fulfilment.pack');
    Route::post('/orders/{order}/fulfilment/dispatch', [FulfilmentController::class, 'dispatch'])->middleware('can:orders.edit')->name('fulfilment.dispatch');
    Route::post('/orders/{order}/fulfilment/pickup', [FulfilmentController::class, 'pickup'])->middleware('can:orders.edit')->name('fulfilment.pickup');
    Route::get('/orders/{order}/fulfilment/files/{type}', [FulfilmentController::class, 'file'])->middleware('can:orders.view')->name('fulfilment.file');
});
