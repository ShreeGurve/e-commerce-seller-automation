# Phase 26 — Supplier Returns & Replacement Management

## Files
- `app/Models/SupplierReturn.php`
- `app/Http/Controllers/SupplierReturnController.php`
- `database/migrations/2026_11_05_000026_create_supplier_returns_table.php`
- `resources/views/purchases/supplier-returns.blade.php`
- `resources/views/purchases/supplier-return-create.blade.php`

## 1. Install
Extract this ZIP into the Laravel project root and merge/overwrite the listed files.

## 2. Add routes
Add these routes inside the existing authenticated route group in `routes/web.php`. **Place them before** the dynamic route `Route::get('/purchases/{purchase}', ...)`.

```php
Route::get('/purchases/supplier-returns', [\App\Http\Controllers\SupplierReturnController::class, 'index'])
    ->middleware('can:purchases.view')->name('supplier-returns.index');
Route::get('/purchases/supplier-returns/create', [\App\Http\Controllers\SupplierReturnController::class, 'create'])
    ->middleware('can:purchases.create')->name('supplier-returns.create');
Route::post('/purchases/supplier-returns', [\App\Http\Controllers\SupplierReturnController::class, 'store'])
    ->middleware('can:purchases.create')->name('supplier-returns.store');
Route::patch('/purchases/supplier-returns/{supplierReturn}/status', [\App\Http\Controllers\SupplierReturnController::class, 'updateStatus'])
    ->middleware('can:purchases.edit')->name('supplier-returns.status');
```

Keep all Phase 25 quality-control routes above `/purchases/{purchase}` as well. Do not replace the whole `routes/web.php` with an older copy.

## 3. Migrate and clear caches
```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=purchases/supplier-returns
```

## 4. Open
`/purchases/supplier-returns`

## Behaviour and safeguards
- Returns can only be created against a Phase 25 quality inspection with rejected quantity.
- Quantity is checked against the inspection's rejected quantity, less quantities already allocated to non-cancelled returns.
- Workflow: DRAFT → APPROVED → DISPATCHED → REPLACED / REFUNDED / CREDITED → CLOSED. Drafts can be cancelled; approved returns can be cancelled before dispatch.
- Supplier reference and notes can be updated with each status change.
- **This module does not change inventory, purchase received quantities, supplier balances, or marketplace data.** Replacement receipt and financial settlement remain manual follow-up tasks; this avoids double posting or inventing accounting entries.
- Requires Phase 25 migration/table `purchase_quality_inspections` to have been installed first.

## Validation
PHP syntax checks and ZIP integrity were run when packaging. Local database/browser testing must be completed in the user's environment.
