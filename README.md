# Seller Automation — Package 15
## Marketplace Financial Analytics & Profitability Intelligence

### What this adds
A read-only finance dashboard at `/finance/marketplace-analytics` that consolidates the existing financial records:
- Order profitability: orders, units, revenue, COGS, marketplace fees, returns, recoveries, advertising cost and profit.
- Actual-profit coverage versus estimated-profit rows.
- Settlement count and net settlement value for the selected period.
- Recorded bank credits, matched credits and payouts needing review (uses Package 14's `marketplace_bank_payouts` table).
- Settlement fee breakdown by fee kind.
- Refund totals, approved claim recovery and recorded operating expenses.
- Marketplace-level revenue/profit comparison and estimated operating profit after recorded expenses.
- Date range and marketplace filters.

### Important accounting definitions
- Profit is sourced from existing `order_profits`: `actual_profit` when present, otherwise `estimated_profit`.
- Actual-profit coverage is the percentage of order-profit lines with a non-null `actual_profit`.
- Settlement totals and bank credits are displayed as reconciliation context; they are not added to sales revenue or profit.
- Operating profit shown is a management estimate: selected order-profit total less recorded expenses. It is not a statutory P&L, tax report, cash balance, or accounting ledger.
- Refund and approved-claim figures are separate operational totals. They are not added a second time to profit, because the existing ProfitService already incorporates refund and recovery effects in its profit rows.
- This package does not recalculate or modify profit records, settlements, refunds, claims, payouts, inventory or accounting balances.

### Files
- `app/Services/MarketplaceFinancialAnalyticsService.php`
- `app/Http/Controllers/MarketplaceFinancialAnalyticsController.php`
- `resources/views/finance/marketplace-analytics.blade.php`
- `tests/Feature/MarketplaceFinancialAnalyticsTest.php`

No migration is required. Package 14's `marketplace_bank_payouts` table must be migrated before this dashboard is used.

### Route to add
Add inside the authenticated routes group in `routes/web.php`:

```php
Route::get('/finance/marketplace-analytics', [\App\Http\Controllers\MarketplaceFinancialAnalyticsController::class, 'index'])
    ->middleware('can:finance.view')
    ->name('finance.marketplace-analytics.index');
```

### Installation
1. Back up the project.
2. Extract the ZIP at the Laravel project root (archive paths start with `app/`, `resources/`, and `tests/`).
3. Confirm Package 14 migration has run. This package has no new migration.
4. Add the route above.
5. Run:
   ```powershell
   php artisan optimize:clear
   $env:DB_CONNECTION="sqlite"
   $env:DB_DATABASE=":memory:"
   php artisan test --filter=MarketplaceFinancialAnalyticsTest
   php artisan test
   ```
6. Open `/finance/marketplace-analytics` with a user who has `finance.view` permission.

### Dependencies / compatibility
- Requires existing tables: `order_profits`, `orders`, `settlements`, `settlement_items`, `refunds`, `claims`, `expenses`, `marketplaces`.
- Requires Package 14's `marketplace_bank_payouts` table.
- Uses existing `Marketplace`, `OrderProfit`, and finance schemas; does not replace models.
- Dashboard is read-only. It intentionally avoids writing to financial source tables.
