<?php
// Add inside the existing authenticated route group in routes/web.php.
// Place the /orders/operations routes BEFORE /orders/{order}.
use App\Http\Controllers\OrderOperationsController;

Route::get('/orders/operations/sla', [OrderOperationsController::class, 'sla'])
    ->middleware('can:orders.view')->name('orders.operations.sla');
Route::get('/orders/operations/fulfillment-exceptions', [OrderOperationsController::class, 'fulfillmentExceptions'])
    ->middleware('can:orders.view')->name('orders.operations.fulfillment-exceptions');
Route::get('/returns/operations/intake-monitor', [OrderOperationsController::class, 'returnIntake'])
    ->middleware('can:returns.manage')->name('returns.operations.intake-monitor');
