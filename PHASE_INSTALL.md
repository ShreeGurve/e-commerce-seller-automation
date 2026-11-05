# Seller Automation — Marketplace Operations Phase 1

## Included
- Internal marketplace operations dashboard with mapping counts, filters and pagination.
- Create/remove local product-to-marketplace SKU mappings.
- Navigation link in the Sales menu.
- Uses existing database tables; no migration required.

## Install
1. Back up your current project.
2. Extract this ZIP into the Laravel project root, preserving folder structure and overwrite the included files.
3. Run `php artisan optimize:clear`.
4. Run `php artisan route:list --name=marketplaces.operations`.
5. Open `/marketplace-operations` after login.

## Permissions
- View: `products.view`
- Add/remove mapping: `products.edit`

## Safety
This is internal/local catalogue management only. It does not call Amazon or Flipkart APIs, publish listings, change live prices or synchronize inventory. Seller account verification is not required.

No migrations are included. Existing `product_marketplace`, `products`, `marketplaces` and `listings` tables are used.


# Seller Automation — Marketplace Operations Phase 2

## Scope
This update extends the existing Listing Management module with an internal, version-specific review workflow.

### Included
- Additive `listing_reviews` table.
- Review request tied to the exact `listing_versions` record.
- Approve or request changes with reviewer notes.
- Review history on the listing detail page.
- Review status in the listing list.
- Approval gate: a version must be approved before it can be marked as live.
- New versions require their own review; older approvals do not approve later edits.
- No Amazon/Flipkart API calls and no marketplace publishing.

## Installation
1. Back up the project and database.
2. Extract this ZIP into the Laravel project root, preserving folders.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=listings
```

Do not run `migrate:fresh`.

## Workflow
1. Open Listings and create or edit a product/channel listing.
2. Save the content. Each save creates a new immutable version.
3. Open the listing and choose **Submit current version for review**.
4. A user with `listings.manage` can approve it or request changes with a note.
5. After approval, use **Mark live** only after manually applying that approved version on the marketplace.

## Permissions
The existing `listings.manage` permission is used for submitting and actioning reviews. No new permission records or seeder changes are required.

## Notes
- The migration is additive and creates only `listing_reviews`.
- Existing listing, listing_versions, product, marketplace and connection data are not modified.
- No API credentials are read or changed.
- Runtime migration and browser tests must be run in your local Laravel environment.


# Seller Automation — Phase 3: Channel-wise Pricing Engine

## Scope
This is an additive refinement of the existing channel pricing engine. It preserves the existing Marketplace Operations routes and any Phase 2 listing review files in the user's local project.

## Included
- Channel-specific outbound shipping and handling remain included in every channel recommendation when configured.
- The `auto_price_enabled` control now has a clear, functional meaning: when enabled, the Pricing engine uses that channel's desired minimum and target profit per unit (₹).
- When disabled, the engine uses the global minimum and target margin percentages, while still including channel shipping and handling costs.
- Pricing detail page has a direct link to Product → Channel Pricing.
- Clear explanation on the pricing detail page showing which target mode is active.
- Feature test covering the disabled-target mode and confirming channel logistics costs still affect break-even.

## Safety
- This is an internal pricing recommendation only.
- No Amazon or Flipkart API calls are made.
- No marketplace price is published or changed.
- `push_enabled` remains forced off by the existing save logic.
- No database migration is included.
- No routes file is included, so the Marketplace Operations route fixes already applied locally are not overwritten.

## Install
1. Back up the project.
2. Extract this ZIP into the Laravel project root, preserving folder structure and replacing only the included files.
3. Run:

```bash
php artisan optimize:clear
```

4. Open a product → Channel Pricing. Configure shipping and handling for Amazon and/or Flipkart.
5. Enable **Use channel-specific profit targets** if you want the rupee-per-unit min/target amounts used. Leave it off to use global margin settings.
6. Open Pricing, select the marketplace, and inspect the recommendation.

## Validation
- PHP syntax checks passed for the changed PHP files.
- PHPUnit was not run here because the project archive does not include `vendor/`. If running locally, use a dedicated in-memory SQLite test database only:

```bash
php artisan test --filter=PricingTest
```

Do not run the RefreshDatabase-based tests against your live MySQL database. Do not run `migrate:fresh`.

# Seller Automation — Phase 4: Marketplace Listing Readiness & Bulk Review Export

## Included
- Internal preflight checks against each listing's current saved version.
- Blockers vs warnings with pass/fail details.
- Filterable overview by marketplace, listing status, SKU or product name.
- CSV review export for offline review.
- No database migration; no changes to existing listing, review, pricing, or marketplace connection data.
- No marketplace API calls, publication, price changes, or inventory changes.

## Important scope boundary
These are generic content completeness checks, **not** official Amazon or Flipkart schema validation. The CSV is a review worksheet, **not** a marketplace upload feed. Category-specific attributes, browse nodes, variation rules, image URL requirements and official feed schemas must be implemented against authorized platform documentation before live submission.

## Install
1. Back up the project.
2. Extract the ZIP into the Laravel project root, preserving paths.
3. Add this line at the bottom of `routes/web.php`, outside any existing route group:

```php
require __DIR__ . '/marketplace_listing_phase4.php';
```

4. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=listing-readiness
```

5. Open `/listing-readiness`.

The routes use the existing `listings.view` permission. Ensure the logged-in role has this permission.

## Routes
- `GET /listing-readiness` — `listing-readiness.index`
- `GET /listing-readiness/export` — `listing-readiness.export`

## Checks
Blockers: missing saved version, empty title, no product image recorded, missing seller SKU.
Warnings: fewer than five bullets, description under 100 characters, missing brand, missing category, quality score below 70.

These are internal defaults and should be tuned to each category and official marketplace rules.

## Safety
No migration is included or required. Existing Phase 2 review workflow and Phase 3 channel pricing are untouched. Do not run `migrate:fresh`.

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

## PHASE 7 — SUPPLIER REORDERING & PURCHASE PLANNING

This patch builds on the supplier, purchase-order and inventory modules already present in the uploaded project. It adds:
- Reorder Planning screen at /reorders.
- Suggestions for active products at/below reorder_level (fallback: minimum_stock).
- Suggested target stock is 2x the configured threshold.
- Outstanding quantities on ORDERED POs are deducted; DRAFT POs are intentionally excluded.
- Create a DRAFT purchase order for a selected supplier and selected products. No email, supplier API call, or external order placement occurs.
- Partial purchase receiving: enter the delivered quantity for each line; only that quantity is posted through InventoryService. Remaining units stay outstanding. The PO becomes RECEIVED only when every line is fully received.
- Full receiving remains available through the existing receive endpoint/service call and now receives only outstanding quantities.

INSTALL
1. Back up your project and database.
2. Extract this ZIP into the Laravel project root, preserving folders, and replace the included files.
3. Run:
   php artisan optimize:clear
   php artisan route:list --name=reorders
   php artisan route:list --name=purchases.receive-quantities
4. Open /reorders. Ensure products have inventory thresholds configured and active suppliers exist.
5. Select a supplier, review/edit quantities and unit costs, then create a DRAFT PO. Review it under Purchases and manually mark it ORDERED by editing/saving the PO status when ready.
6. On a PO detail page, use Receive quantities for partial deliveries.

NO NEW MIGRATION REQUIRED. The uploaded project already contains suppliers, purchases, purchase_items, inventory and the Phase 6 inventory_stock_counts migration.

IMPORTANT
- Do not run migrate:fresh.
- Suggested unit cost is prefilled from current COGS; verify it against the supplier quote before ordering. GST is set to 0 on generated draft lines and can be edited on the PO before it is ordered.
- Only ORDERED purchase orders count as incoming stock in reorder calculations.
- No marketplace API calls, supplier communications, payment transactions or real supplier orders are triggered.

VALIDATION
PHP syntax lint and ZIP integrity were checked for the patch. Runtime database/browser testing was not possible in this environment; verify on your local copy after installation.

PHASE 8 — SUPPLIER PERFORMANCE, PURCHASE ANALYTICS & COST TRACKING

This patch adds supplier performance analytics on top of the existing supplier, purchase and Phase 7 reorder modules.

FEATURES
- Supplier scorecards: purchase count/value, units ordered/received, delivery performance, overdue ordered POs and internal review average.
- Expected delivery date on purchase orders. On-time rate is evaluated only for RECEIVED POs that have an expected delivery date.
- Cost comparison: compares the two latest purchase-line unit costs for each product in the selected date range.
- Supplier detail page with purchase history and internal review history.
- Internal 1–5 ratings for delivery reliability, product quality and pricing competitiveness.
- CSV export of supplier performance.

INSTALL
1. Back up the project and database.
2. Extract/merge the ZIP into the Laravel project root, preserving folders. Keep your .env file.
3. Run:
   php artisan migrate
   php artisan optimize:clear
4. Verify routes:
   php artisan route:list --name=supplier-analytics
5. Open:
   /supplier-analytics

IMPORTANT NOTES
- Expected delivery date is optional. Existing historical POs remain valid; they are excluded from on-time rate until an expected date is recorded.
- On-time means the PO was fully received on or before the expected delivery date. Partial receipts do not count as completed deliveries.
- Overdue means status ORDERED and expected delivery date is before today.
- Cost comparison uses unit_cost excluding GST and compares the two most recent purchase lines within the selected period; it is a purchase-history comparison, not a supplier quote feed.
- Ratings are internal subjective reviews and are not generated automatically.
- No external supplier API, emails, purchase placement, payments or marketplace API calls occur.
- No existing Phase 8 reports/automation are replaced. This adds a separate Supplier Analytics section.

VALIDATION
PHP syntax lint and ZIP integrity should be checked after extraction. Database/browser runtime testing must be performed locally using the project's configured MySQL database. Do not run migrate:fresh.

# Phase 9 — Marketplace connector groundwork (patch only)

This archive contains only the new/changed files. It is intended to be merged into
`seller-automation-update18-audit-marketplace-groundwork.zip`, not used as a full
replacement project.

## Included
- `MarketplaceHttpClient`: HTTPS-only base URL validation, bounded timeout and retry configuration.
- `AmazonLwaTokenProvider`: refresh-token exchange for LWA access token. This does **not** implement SP-API AWS SigV4 signing or marketplace operations.
- `MarketplaceWriteGate`: centralized feature-flag guard for inventory pushes and approval guard for price pushes.
- Explicit per-marketplace API-call flags defaulting to false.
- Readiness response reflects flags without returning secret values.
- Unit tests for safe write defaults.

## Current limits (important)
- Amazon seller API access is not configured. LWA token retrieval cannot succeed without authorized credentials, and actual SP-API calls still need operation-specific roles, regional endpoint configuration and request signing.
- Flipkart seller API access is not configured. The generic HTTP transport is only groundwork; do not assume endpoints or payloads until access and current official API documentation are available.
- No live pulls or writes are invoked by this patch. Existing CSV import/export behavior is unchanged.
- Inventory sync is **not automatically activated**. It remains off until API access, reconciliation, idempotency and operational tests are complete.
- Price changes are guarded by an approved `ApprovalRequest`; the actual marketplace price operation is not implemented yet.
- These files do not replace your full application.

## Merge and test
1. Back up the application and database.
2. Extract this patch ZIP over the project root from Update 18, preserving `.env`.
3. Review the listed changed files before overwrite.
4. Run `php artisan optimize:clear`.
5. Run `php artisan test --filter=MarketplaceWriteGateTest` and then `php artisan test`.

Do not enable `*_API_CALLS_ENABLED` or `MARKETPLACE_PUSH_ENABLED` in production until seller access and real API operation contracts have been implemented and tested.


# Seller Automation — Phase 9: Inventory Forecasting & Demand Planning

## Included
- Internal demand forecasting from historical order items.
- Test orders (`orders.source = TEST`) are excluded.
- Only CONFIRMED, PACKED, SHIPPED, DELIVERED and CLOSED orders count as demand. Cancelled, returned and refunded orders are excluded.
- Product-level average daily demand and selectable 7/14/30/60-day demand horizon.
- Estimated stock-out dates using available stock less reserved stock.
- Suggested replenishment quantity based on forecast demand during lead time + safety stock days, less current sellable stock and outstanding quantities on ORDERED purchase orders.
- Forecast snapshots and retrospective accuracy comparison after each forecast period completes.
- Sidebar navigation entry: Inventory Forecast.

## Important calculation notes
- Lookback window can be 30, 60 or 90 calendar days, including today.
- Lead time and safety stock days are user-adjustable on the forecast screen; the application does not currently maintain a per-supplier lead-time field.
- Incoming quantities count only purchase items on purchases with status ORDERED. Draft and cancelled purchase orders are excluded.
- Stock position = max(available quantity − reserved quantity, 0) + outstanding ordered purchase quantities.
- Suggested reorder quantity = max(forecast daily demand × (lead time days + safety stock days) − stock position, 0), rounded up to whole units.
- The demand forecast is a simple historical average, not a machine-learning prediction. Promotions, seasonality, stockout-constrained sales and returns are not modelled.
- Accuracy is calculated as 100 − absolute error / actual units × 100. When actual demand is zero, accuracy is 0% if a positive quantity was forecast and 100% if zero was forecast.
- Forecast snapshots are saved per active product. Accuracy becomes available after the forecast end date has passed.

## Installation
1. Back up the application folder and database.
2. Extract/merge this ZIP over the Laravel project root. Preserve `.env` and do not delete existing files.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=inventory-forecast
```

4. Open `/inventory-forecast` under the existing application base URL.
5. Choose lookback, forecast horizon, lead time and safety stock days, then click Calculate.
6. Use Save forecast snapshot to preserve the prediction for later accuracy comparison.
7. Use Reorder Planning to create a draft PO after reviewing supplier, quantities and costs.

## Routes added
- `GET /inventory-forecast` — `inventory-forecast.index` (`purchases.view`)
- `POST /inventory-forecast/snapshot` — `inventory-forecast.snapshot` (`purchases.edit`)

## Safety
- No inventory quantities are modified by forecasting.
- No purchase orders are created automatically.
- No supplier is contacted and no payments are made.
- No marketplace APIs are called.
- Existing manual reorder and purchase workflows remain unchanged.

## Validation
PHP syntax checks and ZIP integrity checks were run for this patch. Runtime/database/browser testing requires the user's local `.env`, MySQL database and Composer dependencies.


# Phase 10 – Intelligent Pricing & Profit Optimization

## Included
- Internal pricing recommendation records with tenant ownership, proposed/current/minimum price, expected per-unit profit and margin.
- Submission from the existing pricing detail screen.
- Minimum-price protection: a recommendation cannot be submitted below the current calculated minimum. If the minimum cannot be calculated, submission is blocked.
- Reviewer approval/rejection with notes and audit logging.
- Approved recommendation can be recorded to internal `price_history` after a fresh minimum-price check.
- Approval and recording never call marketplace APIs or publish a price.
- Existing pricing engine, channel fee rules, competitor price capture and what-if simulation remain in place.

## Install
1. Back up the project and database.
2. Extract this ZIP into the Laravel project root, replacing the included files.
3. Run:
   ```bash
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --name=pricing-recommendations
   ```
4. Open the Pricing page, select a product/channel, and submit a recommendation.
5. A reviewer (Super Admin or user with `settings.manage` / `users.manage`) can approve or reject it in Pricing Recommendations.
6. After approval, a user with `products.edit` can select **Record internal price**. This only adds an internal price-history record. Update the marketplace manually if and when appropriate.

## Notes
- No `migrate:fresh`.
- No marketplace API calls, auto repricing, scraping, supplier calls, or automatic marketplace writes.
- Existing price-history records are not modified.
- Approval applies to internal recommendation workflow only.
- If a product has a brand assigned to another business, non-super-admin users cannot submit a recommendation for it.
- If your current fee/cost configuration cannot produce a safe minimum price, the recommendation is blocked until configuration is corrected.
- PHP syntax and ZIP integrity checks are included in the delivery validation; run Laravel migrations and browser checks in your own environment.

## PHASE 10 – PRICING UI REFRESH

Included:
- resources/views/pricing/index.blade.php

This is a presentation-only refresh of the existing pricing index page.
It preserves the existing controller variables, pricing calculations, filters,
pagination, product detail links, and recommendation/approval link.
No database migration or route changes are required.

Install:
1. Back up resources/views/pricing/index.blade.php.
2. Extract this ZIP into the Laravel project root and replace the included file.
3. Run:
   php artisan view:clear
   php artisan optimize:clear
4. Refresh /pricing.

The KPI values are calculated from the existing $counts collection; no sample
or fabricated business figures are used.

## PHASE 11 – SALES ANALYTICS & BUSINESS DECISION ENGINE

Files:
- app/Http/Controllers/BusinessAnalyticsController.php
- resources/views/analytics/business.blade.php
- routes/web.php (route import + two routes)
- resources/views/layouts/app.blade.php (sidebar entry)

No migration required. Uses existing order_profits, orders, products and marketplaces tables.
TEST-source and CANCELLED orders are excluded. Revenue and profit use stored order-profit snapshots; actual_profit is preferred where present, otherwise estimated_profit.

Install:
1. Back up routes/web.php and resources/views/layouts/app.blade.php.
2. Extract this ZIP into the Laravel project root, replacing included files.
3. Run:
   php artisan optimize:clear
   php artisan route:list --name=analytics.business
4. Open /analytics/business.

Permissions:
- View: reports.view
- Export: reports.export

No marketplace API calls, external competitor calls, automated price changes or supplier orders are performed.

## PHASE 11 SQL FIX

Replace:
app/Http/Controllers/BusinessAnalyticsController.php

Fix:
Renames the aggregate alias `lines` to `line_count`, avoiding the MySQL reserved keyword parse error. No schema or route changes.

Then run:
php artisan optimize:clear

# Phase 12 — Automated Business Alerts & Action Centre

## What this patch adds
- Upgrades the existing Automation page into a Business Action Centre with priority summaries, rule management, alert search/filtering and direct links to the affected record.
- Adds three internal automation triggers:
  - **Purchase order delivery overdue** — ordered POs past their expected delivery date.
  - **Order awaiting dispatch** — self-fulfilled, non-TEST orders still CONFIRMED/PACKED after the configured number of days.
  - **Forecast recommends reorder** — latest saved forecast per product with a suggested reorder quantity.
- Keeps existing low-stock, claim-deadline, negative-profit and advertising ACOS triggers.
- Negative-profit and dispatch checks explicitly exclude TEST orders.
- Uses existing `automation_rules`, `automation_logs`, and Laravel database notifications. No new table or migration.
- All actions are internal review links. No marketplace API calls, listing changes, price publishing, PO submission, or courier actions.

## Files in this patch
- `app/Models/AutomationRule.php`
- `app/Services/AutomationService.php`
- `app/Http/Controllers/AutomationController.php`
- `resources/views/automation/index.blade.php`

## Installation (Windows / Laravel)
1. Take a backup of these four existing files.
2. Extract this ZIP into the Laravel project root and allow the four files to be replaced, preserving the folder structure.
3. From the project root run:

```bash
php artisan optimize:clear
php artisan route:list --name=automation
```

4. Open:

`http://127.0.0.1/laravel/seller-automation/automation`

5. Add rules for the new triggers and click **Run checks now**. Suggested thresholds:
   - Overdue purchase: `0` (any day past expected date)
   - Delayed fulfilment: `2` (days)
   - Forecast reorder: `0` (threshold is not used by this trigger)

## Notes
- The forecast-reorder trigger requires at least one saved Phase 9 forecast snapshot. Visit Inventory Forecast and save a snapshot first.
- Overdue purchase alerts require the PO expected-delivery date to be populated.
- Delayed fulfilment checks only self-fulfilled orders in CONFIRMED or PACKED status. Marketplace-fulfilled orders are not included.
- The current automation log fingerprint behavior is retained: the same rule/record condition is not repeatedly re-created on every run.

## Validation
The patch should be syntax-checked with `php -l` on the four PHP files after extraction. Database/browser behavior must be verified in the user's local environment; no local database credentials are available in this build environment.

# Phase 13 — Smart Reconciliation & Financial Risk Monitoring

## What this patch adds
- Extends the existing Finance > Reconciliation screen; it does not create a duplicate finance module.
- Adds summary cards for open issues, potential exposure (sum of absolute open differences), high-risk/aged issues, and issues open for 7+ days.
- Adds marketplace-wise open issue counts and potential exposure.
- Adds CSV export that respects the current status/type filters.
- Shows issue age beside open reconciliation items.
- Excludes order-linked TEST orders from risk summaries, marketplace risk, and CSV export. Settlement-only/unlinked issues remain visible because they cannot be attributed to a TEST order.
- No schema changes, no migrations, and no external marketplace operations.

## Files in this patch
- `app/Http/Controllers/ReconciliationController.php`
- `resources/views/reconciliation/index.blade.php`

## Installation (Windows / Laravel)
1. Back up the two matching files in your project.
2. Extract the ZIP into the Laravel project root and replace those two files.
3. In `routes/web.php`, locate the existing reconciliation index route:

```php
Route::get('/finance/reconciliation', [ReconciliationController::class, 'index'])->middleware('can:finance.view')->name('reconciliation.index');
```

Immediately below it, add:

```php
Route::get('/finance/reconciliation/export', [ReconciliationController::class, 'export'])->middleware('can:finance.view')->name('reconciliation.export');
```

Do not replace the entire routes file; retain your existing Phase 12 and other route additions.

4. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=reconciliation
php artisan route:list --name=reconciliation.export
```

5. Open:

`http://127.0.0.1/laravel/seller-automation/finance/reconciliation`

Use **Export CSV** to download the current filtered issue list.

## Interpretation
- Potential exposure is the sum of the absolute value of each open issue's `difference`; it is a monitoring indicator, not a confirmed recoverable amount.
- High-risk/aged count includes issues with an absolute difference of at least ₹1,000 OR an age of 7 days or more.
- Marketplace grouping uses the linked settlement marketplace. Issues without a linked settlement appear under “Unlinked / unknown”.
- TEST order-linked issues are excluded. Settlement-only issues remain included where no order is linked.

## Validation
Run `php -l app/Http/Controllers/ReconciliationController.php` after extraction. The build environment has no access to your local database/browser, so live query and UI behavior must be verified in your local app. No migration is required.


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


# Phase 15 — Intelligent Inventory & Cash Flow Planning

## Included
- Internal cash-planning dashboard using non-test, eligible order revenue as a historical run-rate.
- User-adjustable 30/60/90-day lookback and planning horizon, plus an assumed collection percentage.
- Outstanding balances for ORDERED purchase orders, including overdue and due-within-horizon totals.
- Estimated replenishment investment using average non-test demand, available/reserved stock, outstanding ordered quantities, and current product unit costs.
- Indicative planning position = estimated collections − due PO balance − suggested replenishment investment.
- Links to existing purchase, inventory and forecasting records.

## Files
- `app/Http/Controllers/CashPlanningController.php`
- `resources/views/cash-planning/index.blade.php`

## Install
1. Back up the matching files if they already exist.
2. Extract the ZIP into the Laravel project root.
3. Add this route inside the existing `Route::middleware('auth')->group(...)` in `routes/web.php`:

```php
Route::get('/finance/cash-planning', [\App\Http\Controllers\CashPlanningController::class, 'index'])
    ->middleware('can:finance.view')
    ->name('cash-planning.index');
```

4. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=cash-planning
```

5. Open `/finance/cash-planning` under your existing Laravel subfolder URL.

## Important interpretation
- This is an operational planning estimate, not a bank balance, cash ledger, or confirmed settlement forecast.
- Projected revenue uses historical order `total_amount` and eligible statuses only; `source = TEST` and cancelled/returned/refunded-only orders are excluded.
- Expected collections are a user-entered assumption, not a payment prediction.
- Only ORDERED purchase orders are treated as committed; DRAFT orders are excluded.
- Replenishment quantity covers up to 30 days of average historical units, even when the planning horizon is 60/90 days, to avoid generating excessive stock recommendations.
- Missing product cost history results in zero estimated replenishment cost; enter current product costs for a useful investment estimate.
- No purchase order is created, no inventory is changed, and no marketplace API is called.

## Validation
PHP syntax checks and ZIP integrity are checked when packaging. Local database/browser behavior must be verified in the user's environment. No migration is required.

# Phase 16 — Automated Purchase Intelligence & Supplier Payment Planning

## Included
- Supplier-wise outstanding exposure for ORDERED and RECEIVED purchase orders.
- Estimated payment due dates based on invoice date (fallback: purchase date) plus recognised supplier payment terms.
- Priority buckets for overdue, due today, upcoming within selected horizon, and unrecognised terms.
- Supplier exposure summary and filtered CSV export.
- Read-only planning: no payment is recorded and no PO/inventory data is changed.

## Files
- `app/Http/Controllers/SupplierPaymentPlanningController.php`
- `resources/views/supplier-payments/index.blade.php`

## Install
1. Back up matching files if they already exist.
2. Extract the ZIP into the Laravel project root.
3. Add both routes inside the existing authenticated route group in `routes/web.php`:

```php
Route::get('/finance/supplier-payments', [\App\Http\Controllers\SupplierPaymentPlanningController::class, 'index'])
    ->middleware('can:finance.view')
    ->name('supplier-payments.index');

Route::get('/finance/supplier-payments/export', [\App\Http\Controllers\SupplierPaymentPlanningController::class, 'export'])
    ->middleware('can:finance.view')
    ->name('supplier-payments.export');
```

4. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=supplier-payments
```

5. Open `/finance/supplier-payments` under your existing Laravel subfolder URL.

## Due-date interpretation
- `Net 30`, `30 days`, `15d` and similar day-based terms are supported.
- `COD`, `cash on delivery`, `advance`, `immediate`, and `due on receipt` are treated as zero-day terms.
- Terms not matching these patterns are flagged for review, not guessed.
- Invoice date is preferred; purchase date is used if invoice date is missing.
- Only ORDERED and RECEIVED purchase orders with an unpaid balance are included. DRAFT and CANCELLED POs are excluded.
- The export contains all open qualifying POs; dashboard filters do not change export scope.

## Safety
This is an internal planning estimate, not an accounting ledger or legal determination of contractual due dates. It does not submit supplier payments, create purchase orders, update payment records, adjust inventory, or call marketplace APIs. TEST orders are not used; this phase reads purchase-order balances only.

## Validation
Run PHP syntax checks and verify route registration locally. Database/browser behavior has not been tested against the user's local database. No migration is required.

# Phase 17 — Smart Reorder Approval Workflow

## What this adds
- Reuses Phase 7 reorder suggestions and the existing `approval_requests` workflow.
- Changes reorder submission from immediate draft-PO creation to a purchase approval request.
- Captures supplier, requested date, selected products, quantities, unit costs and estimated subtotal in the approval's proposed-value snapshot.
- Revalidates that selected products are active and still at/below their reorder threshold at submission time.
- Adds a Reorder Approvals page. Once an existing approver approves the request, an authorised user can convert it into a DRAFT purchase order.
- Uses a database transaction and row lock to prevent the same approval being converted twice.

## Files
- `app/Http/Controllers/ReorderApprovalController.php` (new)
- `resources/views/reorders/index.blade.php` (updated: submits for approval)
- `resources/views/reorders/approvals.blade.php` (new)

## Installation
1. Back up `resources/views/reorders/index.blade.php` if it exists.
2. Extract the ZIP into the Laravel project root.
3. In `routes/web.php`, inside the existing authenticated route group, add these routes. Keep the existing Phase 7 `GET /reorders` route; replace the old `POST /reorders/purchase` route with the new approval route.

```php
use App\Http\Controllers\ReorderApprovalController;

Route::post('/reorders/approval', [ReorderApprovalController::class, 'store'])
    ->middleware('can:purchases.create')->name('reorders.approval');
Route::get('/reorders/approvals', [ReorderApprovalController::class, 'index'])
    ->middleware('can:approvals.view')->name('reorders.approvals');
Route::post('/reorders/approvals/{approvalRequest}/draft', [ReorderApprovalController::class, 'createDraft'])
    ->middleware('can:purchases.create')->name('reorders.approvals.draft');
```

4. Ensure the `ApprovalController` routes are present and approvers have the existing approval-review permissions. The existing approval page handles approve/reject; this patch does not replace it.
5. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=reorders
php artisan route:list --name=approvals
```

6. Open `/reorders`, select products and submit the plan. Review it under `/approvals`. After approval, open `/reorders/approvals` and click **Create draft PO**.

## Important behaviour
- No new migration is required; it reuses the existing `approval_requests` table.
- The estimate is quantity × unit cost and excludes GST, shipping and other charges. The generated draft PO is recalculated by the existing `PurchaseService`.
- An approved plan creates only a DRAFT PO. It does not mark the PO ordered, send supplier email, submit an external PO, make a payment, or change stock.
- The existing Phase 7 direct draft-PO route may remain in routes for backward compatibility, but the updated reorder screen no longer uses it. Remove or permission-restrict that route if your policy requires all reorder-created POs to pass approval.
- This is an internal purchase workflow; no marketplace API calls are made.

## Validation
PHP syntax and ZIP integrity are checked during packaging. Local database/browser execution is not available here; verify the routes and workflow in your local environment.

# Phase 18 – Supplier Relationship Management

## Included files
- `app/Models/SupplierInteraction.php`
- `app/Http/Controllers/SupplierRelationshipController.php`
- `database/migrations/2026_11_04_000018_create_supplier_interactions_table.php`
- `resources/views/suppliers/relationship.blade.php`

## Install
1. Back up existing files if any, then extract this ZIP into the Laravel project root.
2. In `routes/web.php`, add the import with the other controller imports:

```php
use App\Http\Controllers\SupplierRelationshipController;
```

3. Inside the existing `Route::middleware('auth')->group(...)`, add:

```php
Route::get('/supplier-relationships', [SupplierRelationshipController::class, 'index'])
    ->middleware('can:purchases.view')->name('supplier-relationships.index');
Route::post('/supplier-relationships', [SupplierRelationshipController::class, 'store'])
    ->middleware('can:purchases.create')->name('supplier-relationships.store');
Route::patch('/supplier-relationships/{interaction}/status', [SupplierRelationshipController::class, 'updateStatus'])
    ->middleware('can:purchases.edit')->name('supplier-relationships.status');
```

4. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=supplier-relationships
```

5. Open `http://127.0.0.1/laravel/seller-automation/supplier-relationships`.

## Features
- Supplier communication notes, follow-ups, document references and issue records.
- Due/expiry date tracking and overdue indicators.
- Status workflow: Open, In Progress, Done, Cancelled.
- Supplier, type and status filters; paginated activity register.
- Uses existing `purchases.view`, `purchases.create`, and `purchases.edit` permissions.

## Notes
- The migration creates one new `supplier_interactions` table; index names are deliberately short for MySQL compatibility.
- Document records store a reference number and description only; this phase does not upload or store document files.
- No emails, supplier messages, payments, purchase orders, inventory changes, or marketplace API calls are sent or performed.
- Existing supplier, purchase, and supplier-review records are not modified.

# Phase 19 – Supplier Document Vault & Compliance Management

Additive patch for the Seller Automation Laravel application. No full project replacement is required.

## Included files
- `app/Models/SupplierDocument.php`
- `app/Http/Controllers/SupplierDocumentController.php`
- `database/migrations/2026_11_04_000019_create_supplier_documents_table.php`
- `resources/views/suppliers/documents.blade.php`

## 1. Copy files
Extract this ZIP into the Laravel project root and allow the four files to merge into their matching folders. Do not replace existing supplier or relationship files.

## 2. Add routes
Inside the existing authenticated route group in `routes/web.php`, add:

```php
Route::get('/supplier-documents', [\App\Http\Controllers\SupplierDocumentController::class, 'index'])
    ->middleware('can:purchases.view')->name('supplier-documents.index');
Route::post('/supplier-documents', [\App\Http\Controllers\SupplierDocumentController::class, 'store'])
    ->middleware('can:purchases.create')->name('supplier-documents.store');
Route::get('/supplier-documents/{document}/download', [\App\Http\Controllers\SupplierDocumentController::class, 'download'])
    ->middleware('can:purchases.view')->name('supplier-documents.download');
Route::patch('/supplier-documents/{document}/review', [\App\Http\Controllers\SupplierDocumentController::class, 'review'])
    ->middleware('can:purchases.edit')->name('supplier-documents.review');
```

## 3. Run migration and clear cache

```powershell
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=supplier-documents
```

Expected routes: index (GET), store (POST), download (GET), review (PATCH).

## 4. Open module

`http://127.0.0.1/laravel/seller-automation/supplier-documents`

## Security and behaviour
- Files are stored on Laravel's private `local` disk, never in `public` storage. No `storage:link` is required.
- Downloads pass through an authenticated, permission-protected controller; files are not exposed by public URLs.
- Upload accepts PDF/JPG/JPEG/PNG/WEBP up to 10 MB.
- New documents enter `PENDING`; users with `purchases.edit` can approve, reject or return to pending with notes.
- Expiry indicators show expired and expiring-within-30-days records. This phase displays compliance status in the module; it does not send email or scheduled notifications.
- Deleting or changing a review status does not automatically delete stored files. Supplier deletion cascades the document database records; consider archiving suppliers rather than deleting them when retaining compliance evidence is required.
- No external supplier communications, marketplace calls, payments, purchase orders or inventory changes are performed.

## Verification
PHP syntax and ZIP integrity checks are performed when packaging. Run the migration and verify the routes in your local environment. Database/browser testing is not claimed.

# Phase 20 – Supplier Compliance Alerts & Expiry Reminders

Additive patch for Seller Automation. This builds on the Phase 19 `supplier_documents` table and does not replace Phase 18/19 files.

## Included files
- `app/Console/Commands/NotifySupplierDocumentExpiry.php`
- `app/Notifications/SupplierDocumentExpiryNotification.php`
- `app/Http/Controllers/SupplierComplianceController.php`
- `resources/views/suppliers/compliance.blade.php`
- `routes-console-addition.txt` (one scheduler line to merge into `routes/console.php`)

## 1. Copy files
Extract this ZIP into the Laravel project root and merge the files into their matching folders. Keep the existing Phase 19 document vault files.

## 2. Add the web route
Add inside the existing authenticated route group in `routes/web.php`:

```php
Route::get('/supplier-compliance', [\App\Http\Controllers\SupplierComplianceController::class, 'index'])
    ->middleware('can:purchases.view')
    ->name('supplier-compliance.index');
```

The page links to the existing Phase 19 named routes `supplier-documents.index` and `supplier-documents.download`.

## 3. Schedule the daily reminder
Open `routes/console.php`. Keep the existing `Schedule` import and existing `automation:run` schedule. Add this line:

```php
Schedule::command('suppliers:documents-expiry-notify --days=30')->dailyAt('08:00')->withoutOverlapping();
```

Laravel's scheduler must be invoked by the host once per minute. For local Windows development, either run the command manually each day or keep this running in a separate terminal:

```powershell
php artisan schedule:work
```

On a production Linux host, configure the usual Laravel scheduler cron entry (`* * * * * php /path/to/artisan schedule:run`).

## 4. Clear cache and verify

```powershell
php artisan optimize:clear
php artisan route:list --name=supplier-compliance
php artisan list suppliers
php artisan suppliers:documents-expiry-notify --days=30
```

Expected command: `suppliers:documents-expiry-notify`.

## 5. Open the page

`http://127.0.0.1/laravel/seller-automation/supplier-compliance`

## Behaviour and safety
- The compliance page lists expired documents and documents due within selected 7/30/60-day windows. It includes only PENDING and APPROVED documents with an expiry date.
- The command creates Laravel database notifications for active users who have `purchases.view` or `automation.view`, plus Super Admin users.
- Notifications are deduplicated by document ID, expiry date and expired/upcoming state, so hourly/daily reruns do not repeatedly notify for the same expiry event. If the expiry date changes, a new reminder can be generated.
- Default reminder window is 30 days; manual runs can use `--days=60` etc. The scheduled line uses 30 days.
- Notifications are in-app/database notifications only. No email, SMS, WhatsApp, supplier communication, marketplace API, purchase order, payment or inventory action occurs.
- Uses the existing Phase 19 migration and Laravel notifications table; this phase adds no database migration. If the app does not have Laravel's `notifications` table, check that the existing notifications migration has been run before executing the command.

## Verification note
PHP syntax and ZIP integrity are checked during packaging. Database, scheduler execution and browser rendering must be verified in the user's local environment.

# Phase 21 – Supplier Risk Scoring & Vendor Reliability Intelligence

## Included files
- `app/Http/Controllers/SupplierRiskController.php`
- `resources/views/suppliers/risk.blade.php`

No migration or model changes are required. The score reads the existing supplier, purchase, supplier review, supplier document and supplier interaction records from earlier phases.

## Add routes
Add these routes inside the existing authenticated route group in `routes/web.php`:

```php
Route::get('/supplier-risk', [\App\Http\Controllers\SupplierRiskController::class, 'index'])
    ->middleware('can:purchases.view')
    ->name('supplier-risk.index');

Route::get('/supplier-risk/export', [\App\Http\Controllers\SupplierRiskController::class, 'export'])
    ->middleware('can:purchases.view')
    ->name('supplier-risk.export');
```

## Score model
The internal score is 0–100:
- Delivery reliability: 35%
- Supplier review ratings (delivery, quality, pricing): 35%
- Document compliance: 20%
- Open/overdue supplier issues: 10%

Risk bands: LOW = 80–100, MEDIUM = 60–79, HIGH = below 60.

When a supplier has no history for a component, that component uses a neutral 50-point baseline. Delivery performance is calculated only from received purchase orders that have both an expected delivery date and received timestamp. Document compliance uses approved, non-expired documents as valid. Open and overdue ISSUE interactions reduce the issue score.

This is an internal operational indicator. It is not a credit score, legal compliance certification, or external risk assessment.

## Install
1. Extract and merge the ZIP contents into the Laravel project root.
2. Add both routes above inside the authenticated route group.
3. Run:

```powershell
php artisan optimize:clear
php artisan route:list --name=supplier-risk
```

4. Open:
`http://127.0.0.1/laravel/seller-automation/supplier-risk`

## Notes
- No migrations.
- No external API calls, supplier messages, purchase order submissions, payments, or inventory changes.
- CSV export respects search and risk filters.
- Requires previous supplier, supplier review, purchase, Phase 18 interaction, and Phase 19 document tables to be installed.

# Phase 22 – Smart Supplier Sourcing & Recommendations

## Included files
- `app/Http/Controllers/SupplierSourcingController.php`
- `resources/views/sourcing/recommendations.blade.php`

No migration or model changes are required.

## Routes
Add inside the existing authenticated route group in `routes/web.php`:

```php
Route::get('/supplier-sourcing', [\App\Http\Controllers\SupplierSourcingController::class, 'index'])
    ->middleware('can:purchases.view')
    ->name('supplier-sourcing.index');
```

## Install
1. Extract and merge the ZIP into the Laravel project root.
2. Add the route above inside the authenticated group.
3. Run:

```powershell
php artisan optimize:clear
php artisan route:list --name=supplier-sourcing
```

4. Open:
`http://127.0.0.1/laravel/seller-automation/supplier-sourcing`

## Recommendation method
Compares suppliers with received purchase history for the selected product:
- Delivery reliability: 35%
- Supplier review ratings: 35%
- Historical average unit-cost competitiveness: 30%

The historical cost is calculated from received purchase line items. It is not a live supplier quote. Missing review or delivery history receives a neutral baseline. Scores are decision-support guidance, not guarantees.

## Safety and scope
- No external supplier outreach or quote requests.
- No purchase order creation or submission.
- No payments, stock changes or marketplace API calls.
- Uses the existing `purchase_items`, `purchases`, `suppliers`, and `supplier_reviews` tables.

# Phase 23 – Purchase Cost Intelligence & Price Negotiation

## Included
- `app/Http/Controllers/PurchaseCostIntelligenceController.php`
- `resources/views/purchases/cost-intelligence.blade.php`

No migration or model changes.

## Routes
Add inside the authenticated route group in `routes/web.php`:

```php
Route::get('/purchases/cost-intelligence', [\App\Http\Controllers\PurchaseCostIntelligenceController::class, 'index'])
    ->middleware('can:purchases.view')
    ->name('purchase-cost-intelligence.index');

Route::get('/purchases/cost-intelligence/export', [\App\Http\Controllers\PurchaseCostIntelligenceController::class, 'export'])
    ->middleware('can:purchases.view')
    ->name('purchase-cost-intelligence.export');
```

## Install
1. Extract and merge the ZIP in the Laravel project root.
2. Add both routes inside the authenticated route group.
3. Run:
```powershell
php artisan optimize:clear
php artisan route:list --name=purchase-cost-intelligence
```
4. Open:
`http://127.0.0.1/laravel/seller-automation/purchases/cost-intelligence`

## Method
- Uses received purchase order lines only.
- Groups cost history by product and supplier.
- Compares latest and previous received unit cost.
- Calculates average, low and high historical unit costs.
- Shows an indicative negotiation target at 3% below historical average.
- Supports product, supplier and received-date filters and CSV export.

The 3% target is a planning assumption, not a live quote or guaranteed saving. No supplier communication, price updates, PO creation, payment, inventory change or marketplace API call is made.

Requires existing `purchase_items`, `purchases`, `products` and `suppliers` tables.
# Phase 24 – Purchase Budgeting & Procurement Planning

## Included files
- `app/Models/PurchaseBudget.php`
- `app/Http/Controllers/PurchaseBudgetController.php`
- `database/migrations/2026_11_04_000024_create_purchase_budgets_table.php`
- `resources/views/purchases/budget-planning.blade.php`

## Routes
Add inside the existing authenticated group in `routes/web.php`:

```php
Route::get('/purchases/budgets', [\App\Http\Controllers\PurchaseBudgetController::class, 'index'])
    ->middleware('can:purchases.view')
    ->name('purchase-budgets.index');

Route::post('/purchases/budgets', [\App\Http\Controllers\PurchaseBudgetController::class, 'store'])
    ->middleware('can:purchases.create')
    ->name('purchase-budgets.store');

Route::get('/purchases/budgets/export', [\App\Http\Controllers\PurchaseBudgetController::class, 'export'])
    ->middleware('can:purchases.view')
    ->name('purchase-budgets.export');
```

## Install
1. Extract and merge the ZIP into the Laravel project root.
2. Add all three routes inside the authenticated route group.
3. Run:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=purchase-budgets
```

4. Open:
`http://127.0.0.1/laravel/seller-automation/purchases/budgets`

## Calculation scope
- Monthly budget is an internal amount set by an authorized user.
- Actual spend uses RECEIVED purchase orders grouped by `purchase_date` in the selected month.
- Forecast is the arithmetic average of the previous three calendar months' received purchase totals.
- The view shows budget utilization, remaining/over-budget amount and forecast variance.
- CSV export contains saved monthly budgets.

This phase is overall monthly procurement budgeting only. It does not claim category-wise actuals because purchase-line category mapping is not introduced here.

## Safety
No automatic purchase orders, supplier outreach, payment actions, inventory updates or marketplace API calls.

# Seller Automation — Phase 25: Purchase Receiving Quality Control

## Included
- Internal quality inspection records for received purchase quantities.
- Accepted/rejected quantity capture with validation that the quantities add up to the inspected quantity.
- Prevents inspecting more units than have been received (including quantities inspected in earlier records).
- Defect type and inspection notes.
- Supplier follow-up actions: pending, replacement, refund, credit, or no action.
- Open/passed/resolved statuses, supplier/status filters, summary KPIs.
- Navigation link under Purchases.

## Safety and inventory behaviour
This phase records inspection outcomes only. It does **not** add or deduct inventory, change received quantities, create supplier returns, or submit external requests. This avoids double-counting stock because purchase receiving already updates inventory through the existing purchase service. Use the existing inventory/return workflows for physical stock adjustments.

## Install
1. Back up the project and database.
2. Extract this ZIP into the Laravel project root, preserving folders and overwriting included files.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=purchase-quality
```

4. Open **Purchases → Purchase Quality Control** or `/purchases/quality-control`.

Do not run `migrate:fresh`.

## Permissions
- View: `purchases.view`
- Create inspection: `purchases.create`
- Update supplier follow-up: `purchases.edit`

No new permissions are required.

## Validation notes
- PHP syntax checks and ZIP integrity checks performed for this patch.
- Database migration and browser workflow were not run against the user's local environment.

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

# Phase 27 — Supplier Return Settlement & Replacement Receipt Tracking

## Purpose
Record evidence that a resolved supplier return resulted in a refund, supplier credit, or replacement delivery. This is a tracking/confirmation register only; it deliberately does not post to accounting, change supplier balances, or alter inventory.

## Files
- `app/Models/SupplierReturnSettlement.php`
- `app/Http/Controllers/SupplierReturnSettlementController.php`
- `database/migrations/2026_11_05_000027_create_supplier_return_settlements_table.php`
- `resources/views/purchases/supplier-return-settlements.blade.php`

## Dependencies
Phase 26 must be installed first, including `supplier_returns` table, `SupplierReturn` model, and the Phase 26 routes. Phase 25 quality control routes must remain registered because Phase 26 screens link back to that module.

## Installation
1. Extract/merge this ZIP into the Laravel project root.
2. Add the routes below **inside the authenticated route group** and before the dynamic `/purchases/{purchase}` route. Keep Phase 25 and Phase 26 routes in place and above that dynamic route.

```php
Route::get('/purchases/supplier-return-settlements', [\App\Http\Controllers\SupplierReturnSettlementController::class, 'index'])
    ->middleware('can:purchases.view')->name('supplier-return-settlements.index');
Route::post('/purchases/supplier-return-settlements', [\App\Http\Controllers\SupplierReturnSettlementController::class, 'store'])
    ->middleware('can:purchases.create')->name('supplier-return-settlements.store');
Route::patch('/purchases/supplier-return-settlements/{settlement}/confirm', [\App\Http\Controllers\SupplierReturnSettlementController::class, 'confirm'])
    ->middleware('can:purchases.edit')->name('supplier-return-settlements.confirm');
Route::patch('/purchases/supplier-return-settlements/{settlement}/cancel', [\App\Http\Controllers\SupplierReturnSettlementController::class, 'cancel'])
    ->middleware('can:purchases.edit')->name('supplier-return-settlements.cancel');
```

3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=purchases/supplier-return-settlements
```

4. Open `/purchases/supplier-return-settlements`.

## Workflow
- Select a return already resolved in Phase 26.
- Record a REFUND or CREDIT amount, or the quantity of replacement units received.
- New records begin as PENDING.
- A user with purchase edit permission confirms or cancels the record.
- Confirmed entries appear in summary totals; cancelled entries are excluded.

## Safeguards / limitations
- Does not create journal entries, payment transactions, supplier ledger entries, or inventory movements.
- Replacement quantity is validated against the original return quantity, but stock receipt must still be completed through the existing purchase receiving workflow to avoid duplicate inventory posting.
- Multiple pending records can be entered for one return to support staged settlements; only confirmed records are counted in summary totals. Avoid confirming duplicate supplier evidence.
- No marketplace API calls.

## Validation
PHP syntax and ZIP integrity checks are performed for this patch. Run the migration and browser verification in your local environment.
# Phase 28 – Supplier Invoice Matching & Purchase Reconciliation

## Included
- Captures one supplier invoice against an eligible purchase order.
- Compares invoice total (subtotal + tax + shipping + other charges) with the PO total.
- Applies a configurable rupee tolerance (default ₹0.01).
- Flags variances for review; authorized users can accept the variance or reject the match.
- Dashboard filters and summary KPIs.

## Install
1. Extract this ZIP into the Laravel project root and merge/overwrite the included files.
2. The included `routes/web.php` is the integrated route file carrying Phases 25–28. Back up your current file before replacing it. It retains the existing application routes from the latest supplied baseline and puts all specific purchase feature routes before numeric `{purchase}` routes.
3. Run:
   ```bash
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --name=purchase-invoice-matching
   ```
4. Expected routes: index, create, store, review (4 routes).
5. Open `/purchases/invoice-matching` under the application base URL.

## Controls / scope
- Requires the existing purchase tables and Phases 25–27 migrations for the integrated package.
- One invoice-match record per purchase order in this phase.
- Matching is at invoice-total level; it does not yet perform line-by-line SKU/quantity/tax reconciliation or read uploaded PDFs.
- Does not update purchase amount, payment status, supplier balance, inventory, or accounting ledger.
- No supplier email, payment, marketplace API, or external action is triggered.
- Review routes require `purchases.edit`; viewing/creating use existing purchase permissions.

## Notes
The migration is additive and does not modify existing purchase rows. Existing supplier invoice number/date fields on the purchase are not overwritten.


# Phase 29 – Supplier Invoice Line-Level Reconciliation

## Purpose
Adds line-level matching to Phase 28 invoice records. For each purchase item, record supplier-invoiced quantity and unit cost, then compare them with the received quantity and purchase-order unit cost.

## Prerequisites
- Phases 25–28 installed.
- `purchase_invoice_matches`, `purchase_items`, and `purchases` tables exist.
- The Phase 28 `PurchaseInvoiceMatch` model is present.

## Install
1. Back up `routes/web.php`.
2. Extract this ZIP into the Laravel project root and merge/overwrite the included files.
3. Run:
   ```bash
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --name=purchase-invoice-line-checks
   ```
4. The route-list command should show 2 routes.
5. Open `/purchases/invoice-line-checks` under your existing application base URL.

## Routes included
- `GET /purchases/invoice-line-checks` – list invoice matches and line-check results.
- `POST /purchases/invoice-matching/{match}/line-checks` – save/recalculate line checks.

Both routes are registered inside the authenticated group and before the dynamic `/purchases/{purchase}` routes. Phase 25–28 routes from the Phase 28 integrated route file are retained.

## Matching rules
- Invoice quantity is compared with the purchase item's `received_qty` (not ordered quantity).
- Invoice unit cost is compared with the purchase item's `unit_cost`.
- A line is `MATCHED` when quantity variance is zero and unit-cost variance for the invoice quantity is within ₹0.01.
- Otherwise the line is `REVIEW_REQUIRED`.
- Saving again updates the same purchase-item line check rather than creating duplicates.

## Safeguards
- This phase is a review tool only. It does not change purchase orders, inventory, supplier balances, payments, invoice totals, or accounting entries.
- Enter invoice line quantities and costs from the supplier invoice manually. No OCR or PDF parsing is included.
- No marketplace API or supplier communication is performed.

## Validation
PHP syntax and ZIP integrity checks are performed when packaging. Run the migration and route checks locally before using the module.

# Seller Automation – Package 1 (Phases 30–32)
## Supplier Invoice Automation

This integrated package includes the Phase 25–29 purchase quality, returns, settlement, invoice matching and line reconciliation files/routes, plus the new Phase 30–32 invoice-document workflow.

### Included phases
- **Phase 30 – Invoice document vault:** upload PDF/JPG/JPEG/PNG/WEBP invoices to Laravel's private local storage, retain original filename/MIME/size, and download through an authenticated controller route.
- **Phase 31 – GSTIN checks:** validate GSTIN format, compare supplier GSTIN with the supplier master record, compare state-code prefixes, and capture place of supply and review notes.
- **Phase 32 – Invoice processing review:** centralized invoice-document queue with document status, GST validation status, manual review and navigation to total/line reconciliation.

### Important OCR limitation
This package provides secure document intake and an OCR-ready manual-review workflow. It does **not** run OCR or automatically extract text. No OCR service/library or API credentials were supplied in the project baseline. OCR must be connected and tested separately before claiming automated extraction. Uploaded documents remain private; no public storage symlink is required.

### Prerequisites
- Laravel 13, PHP 8.3+
- Existing Seller Automation database and migrations
- Phases 25–29 installed, including `purchase_invoice_matches`
- Existing `purchases.view`, `purchases.create`, and `purchases.edit` permissions

### Installation
1. Back up `routes/web.php` and the database.
2. Extract the ZIP into the Laravel project root, preserving the directory structure and merging/overwriting included files.
3. Run:
   ```bash
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --path=purchases
   ```
4. Verify the new named routes:
   ```bash
   php artisan route:list --name=purchase-invoice-documents
   ```
   Expected: 4 routes.
5. Open:
   ```
   /purchases/invoice-documents
   ```
   under your existing application base URL.

### Storage and file safety
- Files are stored on Laravel's `local` disk under `private/supplier-invoices/{match_id}`.
- Allowed formats: PDF, JPG, JPEG, PNG, WEBP.
- Maximum upload size: 10 MB.
- Downloads pass through authenticated `purchases.view` route.
- Re-uploading replaces the previous file for that invoice match.

### GST validation scope
- Checks GSTIN structure and state-code positions only.
- Compares supplier GSTIN to the GSTIN saved in the supplier master when present.
- State-code comparison produces a review note; it does not decide tax liability.
- It does not connect to GSTN, validate registration status, determine HSN/SAC rates, or file returns. Confirm tax treatment with the invoice and applicable GST rules.

### Safeguards
- Does not modify purchase totals, stock, supplier balances, payments or accounting ledgers.
- Does not send supplier communications.
- No Amazon/Flipkart API calls.
- Invoice totals and line items continue to be checked through the existing Phase 28/29 modules.

### Verification
Run `php artisan route:list --name=purchase-invoice-documents` and test upload/download using a non-production sample invoice. Check `storage/logs/laravel.log` if a local error occurs.

# Seller Automation — Package 2 (Phases 33–35)

## Included
- **Phase 33 — Supplier Invoice Payment Records:** record manually completed payments against supplier invoices, with date, method and transaction reference; partial payment and remaining balance tracking.
- **Phase 34 — Supplier Credit Notes:** record supplier credit notes, link them to an invoice or supplier return, and apply available credit to an invoice from the same supplier.
- **Phase 35 — Supplier Account Statements:** invoice-level statement showing invoice value, recorded payments, applied credits and outstanding balance, with supplier filter.

## Important operating boundaries
- This is a **manual record-keeping workflow**. It does not initiate bank, UPI, card or cash payments.
- It does not automatically update purchase `amount_paid`, supplier master balances, accounting ledgers, or tax records.
- Payment entries are treated as confirmed records of payments already made externally.
- Credit applications are internal records only.
- No marketplace API activity is enabled.

## Install
1. Back up your database and `routes/web.php`.
2. Extract this ZIP into the Laravel project root, preserving folders and allowing the included `routes/web.php` to replace the existing file.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=supplier-payments
php artisan route:list --name=supplier-credit-notes
php artisan route:list --name=supplier-statements
```

## Pages
- `/purchases/supplier-payments`
- `/purchases/supplier-credit-notes`
- `/purchases/supplier-statements`

Use an authenticated account with the existing `purchases.view`, `purchases.create`, and `purchases.edit` permissions as appropriate.

## Notes
- This package builds on Package 1 (Phases 30–32) and carries forward the integrated routes and selected prior phase files.
- Supplier table is expected to use the existing `id` and `name` columns, and purchases to use `supplier_id`.
- The invoice balance shown here is calculated from invoice total minus confirmed payment records and applied credit notes.
- The statement is invoice-based; it is not a full double-entry accounting ledger or statutory supplier statement.
- Run the commands above and verify in your own local environment. This package has been syntax-checked, but not run against your local database or browser.

# Seller Automation — Package 3 (Phases 36–38)

## Included
- **Phase 36 — Purchase Requisitions:** internal request header and line items, required date, priority, purpose, and approve/reject review.
- **Phase 37 — Supplier Quotation Capture & Comparison:** record quotations against approved requisitions, including item unit costs, availability, shipping, tax, other costs and lead time; compare supplier totals.
- **Phase 38 — Quote Award & Approval Record:** record the selected supplier quote with an award rationale and review history.

## Operating boundaries
- All requisitions and quotations are entered manually.
- No emails or RFQs are sent to suppliers automatically.
- Awarding a quote does **not** submit a purchase order or contact a supplier. Create a purchase order separately through the existing purchase workflow after internal checks.
- No marketplace API activity, stock movement, payment, supplier balance update or accounting posting is performed.
- The award comparison is a decision aid; users should consider availability, lead time, terms and quality, not price alone.

## Install
1. Back up your database and `routes/web.php`.
2. Extract this ZIP into the Laravel project root, preserving folders and replacing the included `routes/web.php`.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=procurement
```

## Page
- `/purchases/procurement`

Access uses the existing `purchases.view`, `purchases.create` and `purchases.edit` permissions.

## Package dependency
This package carries forward the selected prior files and routes from Package 2 (Phases 33–35), including the corrected MySQL foreign-key names for the Phase 34 credit-note migration. Install Package 2 and its migration fix first.

## Validation and scope
- PHP syntax validation passed for all PHP files in this ZIP.
- ZIP integrity checked.
- Not run against your local database or browser.
# Seller Automation — Package 4 (Phases 39–41)

## Included
- **Phase 39 — Warehouse & Location Management:** create internal warehouse, store, 3PL, transit and other locations; maintain SKU-level manual stock balances.
- **Phase 40 — Inter-Warehouse Transfers:** create transfer drafts, dispatch from a source location and receive into a destination location. Stock is deducted on dispatch and added on receipt, with negative source balances blocked.
- **Phase 41 — Cycle Count & Stock Reconciliation:** record physical quantities, compare them with internal system balances, review variances and explicitly finalize a count to apply counted quantities.

## Important boundaries
- These warehouse balances are a separate internal warehouse register using SKU/item text. This package does not assume or alter the existing product stock schema.
- Opening/manual balances are entered by authorized users.
- Transfers affect only this package's internal warehouse balances.
- Finalizing a cycle count overwrites internal balances for the SKUs included in that count. Review the displayed variance before finalizing.
- No marketplace inventory sync, listing update, order pull, supplier PO submission, payment, or external API write is performed.
- This package does not automatically import stock from existing inventory records or purchase receiving. Reconcile initial quantities manually.

## Install
1. Install Packages 1–3 first, including the Package 2 MySQL foreign-key fix.
2. Back up the database and `routes/web.php`.
3. Extract this ZIP into the Laravel project root, preserving folders and replacing the included `routes/web.php`.
4. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=warehouse
```

## Page
- `/warehouse/intelligence`

Access uses existing `products.view` and `products.edit` permissions.

## Validation
- PHP syntax validation passed for all PHP files in this ZIP.
- ZIP integrity checked.
- Not run against your local database or browser.

# Seller Automation — Package 5 (Phases 42–44)

## Included
- **Phase 42 — Executive Intelligence:** operational snapshot for supplier invoice records/payments, procurement requests/quotes, warehouse SKU counts/quantities, transfers and stock-count drafts.
- **Phase 43 — Internal Automation Rules:** configurable low-stock, overdue-requisition and invoice-review rules. Evaluation creates deduplicated internal alerts; users can resolve alerts.
- **Phase 44 — Executive Snapshot Export:** downloadable CSV of the supported operational metrics.

## Important scope and safety
- This is an operational snapshot of the supplier/procurement/warehouse modules in Packages 1–4, not a replacement for the existing sales/profit analytics dashboard.
- It does not query sales/orders, so TEST orders are not included.
- Warehouse quantity is a mixed-unit total and is not a monetary stock valuation.
- Supplier invoice totals are recorded invoice amounts, not revenue, sales or profit.
- Rule evaluation is manual (button click). It creates internal alerts only. No scheduled jobs, email notifications, external API calls, marketplace writes, stock sync, payment, or purchase order creation.
- Access uses existing `purchases.view` and `purchases.edit` permissions.

## Install
1. Install Packages 1–4 first, including the Package 2 MySQL foreign-key fix.
2. Back up your database and `routes/web.php`.
3. Extract this ZIP into the Laravel project root, preserving folders and replacing the included `routes/web.php`.
4. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=executive.intelligence
```

## Page
`/executive/intelligence`

## Validation
PHP syntax and ZIP integrity were checked when packaging. Database/browser execution was not run against your local environment.
