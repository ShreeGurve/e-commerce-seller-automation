# Package 17 — Marketplace Automation & Intelligent Recommendations

Read-only recommendation engine that identifies operational signals from existing Seller Automation data. It does not execute marketplace or internal actions.

## Included

- Low-stock and estimated stock-out risk using available stock (available less reserved) and recent order-profit unit velocity.
- Sales decline comparing the selected period with the immediately preceding period.
- High return-rate signals by product.
- Low recorded profit-margin signals using actual profit when available, otherwise estimated profit.
- Priority summary and configurable analysis thresholds.
- Feature tests for empty state, read-only low-stock signal, and validation.

## Files

- `app/Services/MarketplaceRecommendationService.php`
- `app/Http/Controllers/MarketplaceRecommendationController.php`
- `resources/views/analytics/marketplace-recommendations.blade.php`
- `tests/Feature/MarketplaceRecommendationServiceTest.php`

## Install

1. Back up the application and database.
2. Extract the archive at the Laravel project root.
3. Add this route inside the authenticated routes group:

```php
use App\Http\Controllers\MarketplaceRecommendationController;

Route::get('/analytics/marketplace-recommendations', [MarketplaceRecommendationController::class, 'index'])
    ->middleware('can:finance.view')
    ->name('analytics.marketplace-recommendations.index');
```

4. Ensure the migrations for `order_profits`, `orders`, `products`, `inventory`, `returns`, and `return_items` have already run. No migration is added by this package.
5. Clear route/view caches using your normal MySQL development configuration:

```bash
php artisan optimize:clear
```

6. Run tests using the project's test configuration. For SQLite memory, make sure the test environment uses `CACHE_STORE=array` (or `file`) rather than a database cache store:

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
$env:CACHE_STORE="array"
php artisan test --filter=MarketplaceRecommendationServiceTest
php artisan test
```

7. Open `/analytics/marketplace-recommendations`.

## Logic and safeguards

- `RESTOCK`: available quantity less reserved quantity is at or below the product's minimum stock.
- `STOCK_RISK`: calculated days of cover from period sales velocity is at or below the configured threshold.
- `SALES_DECLINE`: current period units are at least 40% lower than the immediately preceding period, requiring at least 3 prior-period units.
- `HIGH_RETURN_RATE`: returned units divided by units sold reaches the configured threshold.
- `LOW_PROFIT_MARGIN`: recorded line profit margin is below 5%; actual profit is preferred when present, otherwise estimated profit is used.
- Sales/return signals are based on the selected calendar window and available data; they are not marketplace forecasts.
- No writes are made to inventory, pricing recommendations, orders, listings, or finance tables.
- No automated repricing, replenishment purchase order, listing edit, or marketplace API call is performed.

## Verification

PHP syntax and archive integrity should be checked after extraction. PHPUnit must be run in the target Laravel project; this package's tests are not represented as passed until the user reports the result.
