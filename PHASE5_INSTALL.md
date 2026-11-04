# Phase 5 – Marketplace Order Fulfilment (internal workflow)

## Included
- One-to-one fulfilment record per existing order.
- Fulfilment queue for CONFIRMED / PACKED / SHIPPED orders.
- Package weight and dimensions, packing reference and notes.
- Carrier, AWB/tracking URL, invoice number and secure private file storage for label/invoice.
- Controlled workflow: CONFIRMED -> PACKED -> SHIPPED using the existing OrderService transition/history logic.
- Pickup schedule/reference and actual pickup timestamp.
- Fulfilment event audit trail.

## Apply files
Extract this ZIP into the existing Laravel project root, preserving existing files. It contains only changed/new files. Back up first.

Add this at the bottom of `routes/web.php`, outside any existing route group:

```php
require __DIR__ . '/marketplace_fulfilment_phase5.php';
```

The included route file wraps its routes in `auth` middleware.

Run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan route:list --path=fulfilment -v
```

Migration is additive; it creates `order_fulfillments` and `order_fulfillment_events`. It does not drop or rewrite existing order, return, refund, settlement, listing or marketplace tables. Do not run `migrate:fresh`.

Open `/fulfilment-queue` under your existing app base URL. The Orders page also gets a Fulfilment queue link.

## Important limits
- No Amazon SP-API or Flipkart API call is made. No labels are purchased and no pickup is booked automatically.
- Enter label, invoice, carrier and tracking details received from the marketplace/courier. Files are stored on Laravel's private local disk and downloaded through an authenticated route.
- Marketplace-fulfilled (FBA / Flipkart Fulfilled) orders are not allowed through the self-ship pack/dispatch buttons.
- Existing `orders.view` and `orders.edit` permissions are reused; no new permission seeder is required.
- CSV import remains in the existing Imports module; this phase does not duplicate or replace its mapping engine.

## Validation
PHP syntax checked for all included PHP files. Runtime database migration and browser tests must be run in your local Laravel environment.
