# Package 16 — Marketplace Business Intelligence & Performance Dashboard

## Purpose
Adds a read-only dashboard for sales trends, product profitability, low-margin product identification, marketplace comparisons and return performance. It builds on the existing `order_profits`, `orders`, `products`, `returns`, `return_items` and `marketplaces` tables. No migration is required.

## Files
- `app/Services/MarketplaceBusinessIntelligenceService.php` — date- and marketplace-filtered read-only analytics.
- `app/Http/Controllers/MarketplaceBusinessIntelligenceController.php` — validated dashboard endpoint.
- `resources/views/analytics/marketplace-business-intelligence.blade.php` — responsive dashboard view.
- `tests/Feature/MarketplaceBusinessIntelligenceTest.php` — empty-state, date validation and threshold tests.

## Installation
1. Back up the application.
2. Extract the ZIP at the Laravel project root; archive paths start at `app/`, `resources/` and `tests/`.
3. Add this route inside the authenticated route group in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceBusinessIntelligenceController;

Route::get('/analytics/marketplace-business-intelligence', [MarketplaceBusinessIntelligenceController::class, 'index'])
    ->middleware('can:finance.view')
    ->name('analytics.marketplace-business-intelligence.index');
```

4. Clear cached routes/views:

```bash
php artisan optimize:clear
```

5. Run focused and full tests in the project's safe SQLite test environment:

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
php artisan test --filter=MarketplaceBusinessIntelligenceTest
php artisan test
```

6. Open `/analytics/marketplace-business-intelligence` while signed in with `finance.view` permission.

## Metric definitions and limitations
- Profit is taken from existing `order_profits`: `actual_profit` when populated, otherwise `estimated_profit`.
- Monthly trend groups the daily profit rows by calendar month.
- Product ranking is limited to the top 100 products from the query, with the top 10 displayed. Low-margin products are identified within that same result set, not across an unlimited catalogue.
- Return rate is `SUM(return_items.quantity) / SUM(order_profits.qty) * 100` for the selected date range. Returns are filtered by `returns.return_date`; sales are filtered by `order_profits.order_date`. This is a unit-based indicator and can exceed 100% if the selected return period contains returns from older sales.
- Product return ranking uses expected return-item quantity, not only received/inspected quantity.
- No predictive/AI scoring, marketplace API polling, price changes, inventory changes, accounting postings or return status changes are performed.
- No migration is included. Existing Package 13/14/15 functionality remains untouched.

## Verification
PHP syntax checks and ZIP integrity checks are performed during packaging. PHPUnit must be run in the user's Laravel installation; do not treat the tests as passed until local results are reported.
