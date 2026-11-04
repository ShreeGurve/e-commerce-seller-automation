# Phase 5.1 — Internal Test Orders

## Purpose
Adds a dedicated manual test-order workflow so the fulfilment lifecycle can be exercised while Amazon SP-API / Flipkart Seller API approvals are pending.

## Safety behaviour
- Creates orders with `orders.source = TEST` and a generated `TEST-...` external reference.
- Forces `fulfillment_type = SELF` and initial status `CONFIRMED`.
- Never calls Amazon, Flipkart, courier, label, or pickup APIs.
- Does not deduct inventory or write inventory ledger transactions.
- Does not create order-profit rows; global profit recalculation skips TEST orders.
- Excludes TEST orders from daily sales summaries, marketplace 30-day sales KPIs, advertising TACoS sales denominator, and GST sales report.
- Test orders remain visible in Orders and Fulfilment Queue, clearly marked TEST.

## Install
1. Back up the project.
2. Extract files into the Laravel project root, replacing included files.
3. Add this line at the bottom of `routes/web.php`, outside route groups:

```php
require __DIR__ . '/marketplace_test_orders_phase5.php';
```

4. Run:

```bash
php artisan optimize:clear
php artisan route:list --path=test-orders -v
```

No migration is required. Existing `orders.source` is a varchar(10), and `TEST` fits.

## Use
Open Orders and select **Test order**, or visit `/test-orders/create`.
Create an order, then use its order detail page and the existing Fulfilment Queue to test pack, dispatch, and pickup. Test orders are excluded from financial totals and do not change stock.

## Validation
PHP syntax and ZIP integrity checks were run. Browser, database and PHPUnit tests must be run in the user's local Laravel environment.
