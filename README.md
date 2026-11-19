# Seller Automation — Package 3: Read-only Sync Pipeline

This package adds the safe ingestion/staging layer for marketplace catalog, order and inventory reads. It is designed to be installed after Package 1 (stability/migration repair) and Package 2 (tenant-aware credentials).

## Included

- `MarketplaceReadProvider` contract and paginated `MarketplaceReadPage` response object.
- Provider registry keyed by marketplace code (`amazon`, `flipkart`, etc.).
- `MarketplaceReadSyncService` with per-connection/resource locking, pagination guards, idempotent upserts, sync-run history, and sanitized order payload staging.
- `RunMarketplaceReadSync` queue job. Queue payload contains only connection ID, resource and optional date bounds; it never contains credentials.
- Dedicated staging tables for sync runs and normalized raw records.
- Separate marketplace inventory snapshots. Internal `inventory` balances are never modified.
- Feature tests covering idempotency, PII key filtering, inventory isolation and connection validation.

## Important scope boundary

This package implements the read-sync pipeline, staging schema and provider contract. It does **not** include production Amazon SP-API or Flipkart Seller API provider classes. Package 2's Amazon LWA token exchange is not an SP-API request signer; Amazon SP-API requests also require the correct regional endpoint and AWS SigV4 signing. Flipkart operation paths/scopes must match the seller's approved API access. Do not register a provider until those platform-specific requirements have been implemented and verified against the seller account.

Consequently, no live marketplace API calls are made by this package as delivered. The `providers` registry is intentionally empty. This is a deliberate safety boundary, not a claim that marketplace sync is already live.

## Installation

1. Back up the application and database.
2. Confirm Package 1 and Package 2 are installed. Package 1 supplies the `marketplace_connections.environment` column; Package 2 supplies tenant-aware connection credentials and health checks.
3. Extract this ZIP into the Laravel project root, preserving directories.
4. Run:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
```

Review the SQL preview, then run:

```powershell
php artisan migrate
```

Do not use `migrate:fresh` on a working database.

## Queue usage

Once a marketplace-specific read provider has been implemented and registered in `config/marketplace_sync.php`, dispatch a sync job from an authorized controller/service:

```php
use App\Jobs\RunMarketplaceReadSync;

RunMarketplaceReadSync::dispatch(
    $connection->id,
    'orders',       // products | orders | inventory
    '2026-11-01',   // optional from date
    '2026-11-19'    // optional to date
);
```

Start the configured queue worker:

```powershell
php artisan queue:work --tries=3
```

The sync run is stored in `marketplace_sync_runs`. Normalized records are stored in `marketplace_sync_records`. Inventory read results are additionally stored in `marketplace_inventory_snapshots`.

## Provider contract

Create a class implementing `App\Marketplaces\Sync\MarketplaceReadProvider` and return a `MarketplaceReadPage`. Each item must have:

```php
[
    'external_id' => 'stable-marketplace-id-or-sku',
    'payload' => [/* normalized fields; do not include customer PII */],
]
```

Register it by marketplace code:

```php
// config/marketplace_sync.php
'providers' => [
    'amazon' => App\Marketplaces\Amazon\AmazonReadProvider::class,
    'flipkart' => App\Marketplaces\Flipkart\FlipkartReadProvider::class,
],
```

Provider implementations must be read-only and return normalized records. Never include access tokens, refresh tokens, client secrets or authorization headers in returned payloads.

## Safety and data behavior

- Only `products`, `orders`, and `inventory` are accepted.
- Connection status must be `connected`, `active`, or `verified`.
- Sync runs are locked per connection and resource to prevent overlap.
- Pagination is capped by `MARKETPLACE_SYNC_MAX_PAGES` (default 100); repeated/missing cursors are rejected.
- Common customer/buyer/address/contact PII keys are recursively removed before staging.
- Repeated external IDs update the staged row instead of duplicating it.
- Order data is staged only. It does not create operational `orders` or deduct stock.
- Inventory data is stored as a marketplace snapshot only. It does not update the internal `inventory` table.
- No marketplace write endpoints are implemented or invoked.

## Validation

Use the project's safe test database configuration (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`):

```powershell
php artisan test --filter=MarketplaceReadSyncServiceTest
php artisan test
```

The build environment did not contain `vendor/`, so PHPUnit was not run during package assembly. PHP syntax checks and ZIP integrity checks were performed.

## Rollback

1. Stop queue workers.
2. Run `php artisan migrate:rollback --step=1` only if this package's migration is the latest migration and no later migrations have been applied. Otherwise create a reviewed rollback migration or restore the database backup.
3. Remove the files listed in this package and clear caches with `php artisan optimize:clear`.

Do not delete staged sync tables manually if their data is needed for audit/history.
