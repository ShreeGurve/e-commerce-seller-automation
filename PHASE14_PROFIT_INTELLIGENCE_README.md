# Phase 14 — Advanced Profit Intelligence & Product-Level Optimization

## What this patch adds
- Extends the existing Finance > Profit page; no duplicate module or new database tables.
- Adds product-level intelligence counters for loss-making products, positive but thin-margin products (below 10%), and high-return products (refunds above 15% of revenue).
- Highlights the highest positive profit contributor in the selected product result set.
- Adds a filtered product profitability CSV export with units, revenue, COGS, marketplace fees, refunds, ad cost, profit and margin.
- Explicitly excludes TEST and CANCELLED orders from the profit report aggregates and CSV export.
- Preserves existing fee calculations, actual-vs-estimated profit logic, filters and recalculate workflow.
- No marketplace API calls, price publishing or automatic price changes.

## Files in this patch
- `app/Http/Controllers/ProfitController.php`
- `app/Services/ProfitService.php`
- `resources/views/profit/index.blade.php`

## Installation (Windows / Laravel)
1. Back up the three matching files in your project.
2. Extract this ZIP into your Laravel project root and replace the matching files.
3. Add this route immediately below the existing `profit.index` route in `routes/web.php` (inside the authenticated route group):

```php
Route::get('/finance/profit/export', [ProfitController::class, 'export'])
    ->middleware('can:finance.view')
    ->name('profit.export');
```

Keep the existing `profit.index` and `profit.recalculate` routes and all Phase 12/13 routes. The `ProfitController` import already exists in the project's routes file.

4. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=profit
php artisan route:list --name=profit.export
```

5. Open:

`http://127.0.0.1/laravel/seller-automation/finance/profit`

Use **Export product analysis CSV** to download the current filtered product analysis.

## Interpretation
- Thin margin means profit is positive but below 10% of revenue.
- High returns means refund amount exceeds 15% of revenue.
- These are review thresholds, not automatic pricing decisions.
- The top contributor is calculated from the product rows returned by the existing profitability report (up to its current 300-product display limit).
- Profit figures continue to use actual settlement profit when available and estimated profit otherwise.

## Validation
- Run `php -l app/Http/Controllers/ProfitController.php` and `php -l app/Services/ProfitService.php` after extraction.
- The build environment does not have the user's local database or browser, so database and UI behavior must be verified in the local app.
- No migration is required.
