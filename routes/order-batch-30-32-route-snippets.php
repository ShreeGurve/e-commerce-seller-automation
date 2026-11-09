<?php
// Add these routes inside the existing Route::middleware('auth')->group() in routes/web.php.
// Keep these static routes before /orders/{order} and /returns/{productReturn}.

use App\Http\Controllers\OrderOperationsInsightsController;

Route::get('/orders/operations/carrier-handover', [OrderOperationsInsightsController::class, 'carrierHandover'])
    ->middleware('can:orders.view')->name('orders.operations.carrier-handover');
Route::get('/orders/operations/delivery-aging', [OrderOperationsInsightsController::class, 'deliveryAging'])
    ->middleware('can:orders.view')->name('orders.operations.delivery-aging');
Route::get('/returns/operations/disposition-reconciliation', [OrderOperationsInsightsController::class, 'returnDisposition'])
    ->middleware('can:returns.view')->name('returns.operations.disposition-reconciliation');
