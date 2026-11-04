# Phase 6 — Inventory Stocktake & Reconciliation

## What this patch adds
- Record a physical count for an individual product.
- Capture the system quantity at the time of count, physical quantity, variance, operator and reason.
- Apply the variance through the existing InventoryService as an immutable ADJUSTMENT ledger transaction.
- Show recent stock count history on the product inventory page.
- No marketplace API calls and no marketplace stock publishing.

## Install
1. Back up project and database.
2. Extract the ZIP into the Laravel project root.
3. Merge the supplied InventoryController.php changes into your current controller if you have made local customizations; otherwise replace it.
4. Add the route below inside the existing authenticated route group in routes/web.php:

```php
Route::post('/inventory/{product}/stock-count', [InventoryController::class, 'stockCount'])
    ->middleware('can:inventory.manage')->name('inventory.stock-count');
```

5. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=inventory.stock-count
```

Do not run migrate:fresh.

## Workflow
Inventory → open a product → enter the physically counted quantity and a mandatory reason → Record count.
The system calculates variance against the locked current available quantity. A positive variance adds stock; a negative variance reduces stock. The adjustment and count record are committed together. Negative resulting stock is not allowed.

## Safety
- Existing stock buckets are changed only through InventoryService.
- Every accepted count creates a ledger transaction when variance is non-zero.
- Zero variance is recorded in count history without creating a zero-quantity ledger entry.
- The count is a manual physical stocktake; it is not a warehouse scan or marketplace stock sync.
- Test/manual stock remains internal. No external APIs are called.
