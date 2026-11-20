# Package 20 — Marketplace Write Adapters & Two-Person Live Control

## Scope

Adds narrowly scoped, audited Amazon SP-API Listings Items PATCH and Flipkart Listing Management v3 price/inventory write adapters. A write is **not** triggered from an approved recommendation alone. The workflow is:

1. An approved Package 18 task is selected.
2. A user prepares an immutable local preview. No marketplace API call is made.
3. A different user approves and submits the exact payload.
4. A Super Admin must separately enable that connection + operation policy.
5. Multiple server-side environment flags must be enabled.

The default state is fail-closed. This package does not enable any live writes during migration.

## Included files

- `database/migrations/2026_11_19_000013_create_marketplace_write_execution_tables.php`
- `config/marketplace_writes.php`
- `app/Marketplaces/Writes/AmazonListingsWriteAdapter.php`
- `app/Marketplaces/Writes/FlipkartListingsWriteAdapter.php`
- `app/Services/MarketplaceWriteExecutionService.php`
- `app/Http/Controllers/MarketplaceWriteExecutionController.php`
- `resources/views/marketplaces/write-execution.blade.php`
- `tests/Feature/MarketplaceWriteExecutionServiceTest.php`

## Routes

Add inside the existing authenticated route group in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceWriteExecutionController;

Route::middleware(['auth', 'can:listings.manage'])
    ->prefix('marketplace-writes')
    ->name('marketplaces.writes.')
    ->group(function () {
        Route::get('/', [MarketplaceWriteExecutionController::class, 'index'])->name('index');
        Route::patch('/connections/{connection}/policies/{operation}', [MarketplaceWriteExecutionController::class, 'togglePolicy'])->name('policy');
        Route::post('/prepare', [MarketplaceWriteExecutionController::class, 'prepare'])->name('prepare');
        Route::post('/attempts/{attempt}/approve-execute', [MarketplaceWriteExecutionController::class, 'approveExecute'])->name('approve-execute');
    });
```

Open `/marketplace-writes`.

## Installation

1. Back up the project and database.
2. Extract this ZIP at the Laravel project root.
3. Add the routes above.
4. Run against the normal MySQL development configuration:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

5. Test with isolated SQLite memory and array cache:

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
$env:CACHE_STORE="array"
php artisan test --filter=MarketplaceWriteExecutionServiceTest
php artisan test
```

## Supported operations

### Amazon SP-API

- `PRICE_UPDATE`: PATCH `/listings/2021-08-01/items/{sellerId}/{sku}` with a `purchasable_offer` attribute patch.
- `INVENTORY_UPDATE`: same Listings Items PATCH operation with a `fulfillment_availability` attribute patch.
- Requires a connected Production Amazon connection with `seller_id`, `marketplace_id`, `region`, LWA credentials and product `product_type`. This package intentionally supports Amazon India only (`A21TJRUUN4KGV`, INR) until other marketplace currency mappings are added.
- `INVENTORY_UPDATE` targets the seller-fulfilled `DEFAULT` channel; FBA/marketplace-managed stock is not changed.
- `patchListingsItem` is for an existing listing; it does not create a new listing.
- Amazon India uses the Europe regional SP-API endpoint (`sellingpartnerapi-eu`). The adapter selects the endpoint from the saved connection region.
- HTTP 200 is not sufficient: the response body must contain `status=ACCEPTED`. An `INVALID` response is recorded as failed. An accepted submission is not “live/complete”; Amazon may process it further and later surface issues. Verify the listing in Amazon before treating it as completed.

### Flipkart Seller API

- `PRICE_UPDATE`: POST `/listings/v3/update/price` with one SKU per request. The product mapping must contain a 13–16 digit `external_product_id`; MRP and selling price must be positive whole INR values.
- `INVENTORY_UPDATE`: POST `/listings/v3/update/inventory` with one SKU and a location ID from Flipkart Onboarding. Quantity must be a non-negative integer.
- Only Production connections are supported in this release. HTTP 200 is not sufficient: the response's per-SKU `status` must be `success` or `warning` to record `ACCEPTED`.

## Environment gates (all false by default)

Add these to `.env` only after reviewing and testing the application:

```env
MARKETPLACE_WRITES_ENABLED=false
AMAZON_SP_API_WRITE_ENABLED=false
FLIPKART_SELLER_API_WRITE_ENABLED=false
MARKETPLACE_WRITE_ATTEMPT_TTL_MINUTES=30
```

Live submission also requires the existing API-call gates (the older global `MARKETPLACE_PUSH_ENABLED` flag is not used by this package, to avoid enabling legacy push workflows):

```env
AMAZON_SP_API_ENABLED=true
AMAZON_SP_API_CALLS_ENABLED=true
FLIPKART_SELLER_API_ENABLED=true
FLIPKART_SELLER_API_CALLS_ENABLED=true
```

Only enable the flags for a platform that has completed seller authorization and operation-specific API role/scope review. Do not enable both platform write flags just to test one marketplace. Clear config cache after changes.

## Safety and audit behaviour

- Migration creates policy rows only when a Super Admin enables a specific connection/operation; no policy is seeded as enabled.
- Only a connected Production connection is eligible for live submission.
- Only approved `LOW_PROFIT_MARGIN` tasks can prepare a price update; only approved `RESTOCK` or `STOCK_RISK` tasks can prepare inventory updates.
- Product must belong to the authenticated user's business (`products.created_by` → `users.business_id`) and have a marketplace SKU mapping. Legacy products with a null creator are deliberately excluded until ownership is assigned.
- A second user must approve the exact payload. The SHA-256 payload hash is rechecked before submission.
- Attempts expire after the configured preview TTL. Idempotency keys are unique and cannot be reused for a different payload/connection/operation.
- No automatic retry is performed for writes. Transport errors, HTTP 429 and HTTP 5xx are recorded for manual review because remote outcome can be uncertain.
- Secrets and access tokens are never copied into attempt payloads or audit responses.
- No automatic rollback is attempted; if a marketplace accepts a write, correct it with a separately reviewed compensating update if needed.
- No order, return, refund, settlement or accounting operation is implemented.
- This package does not execute Package 19's generic `LIVE` endpoint. That endpoint remains blocked. Live writes use this separately gated, explicitly reviewed workflow.

## API documentation used

- Amazon Listings Items `patchListingsItem`: https://developer-docs.amazon.com/sp-api/reference/patchlistingsitem
- Amazon regional endpoints: https://developer-docs.amazon.com/sp-api/docs/sp-api-endpoints
- Amazon Listings Items guide and attribute examples: https://developer-docs.amazon.com/sp-api/docs/manage-product-listings-guide
- Flipkart Listing Management v3 overview: https://seller.flipkart.com/api-docs/listing-api-docs/v3/Overview.html
- Flipkart update price: https://seller.flipkart.com/api-docs/listing-api-docs/v3/UpdateListingsPriceAPI.html
- Flipkart update inventory: https://seller.flipkart.com/api-docs/listing-api-docs/v3/UpdateListingsInventoryAPI.html

## Verification

PHP syntax and archive integrity are checked before delivery. PHPUnit must be run in the target Laravel environment; do not treat tests as passed until the local output confirms it.
