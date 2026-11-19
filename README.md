# Seller Automation — Package 2: Tenant-Aware Marketplace Credentials

This package builds on the latest Seller Automation baseline and the stability/migration package. It contains only the files added or changed for Package 2.

## Included

- `MarketplaceConnectionResolver`: resolves a connection by business, marketplace code and environment, keeping the query inside the requested business boundary.
- `AmazonLwaTokenProvider::accessTokenForConnection()`: exchanges the encrypted credentials belonging to one Amazon connection for an LWA access token. The existing `.env`-based `accessToken()` method remains available for backwards compatibility.
- `FlipkartAccessTokenProvider`: exchanges the encrypted credentials belonging to one Flipkart connection for an access token.
- `ConnectionTokenCache`: caches tokens by business + connection + one-way credential fingerprint. A credential change naturally creates a new cache key; raw secrets are never placed in cache keys.
- `MarketplaceConnectionHealthChecker`: rechecks the saved credentials and updates the connection status and last-check details.
- Marketplace connections page: shows the current connection state and adds a **Check connection** action.
- Feature tests for tenant isolation, token caching, and API-call safety flags.

## Installation

1. Back up the project and database.
2. Extract the ZIP into the Laravel project root, preserving the folder structure.
3. Add the route below inside the existing authenticated route group in `routes/web.php`, beside the other marketplace-connection routes:

```php
Route::post('/marketplace-connections/{connection}/recheck', [MarketplaceConnectionController::class, 'recheck'])
    ->middleware('can:settings.manage')
    ->name('marketplaces.connections.recheck');
```

`MarketplaceConnectionController` is already imported in the current baseline's `routes/web.php`.

4. Clear cached application metadata:

```powershell
php artisan optimize:clear
```

5. Run the focused tests and then the full suite:

```powershell
php artisan test --filter=MarketplaceConnectionCredentialTest
php artisan test
```

## Required configuration and safety

The connection-aware providers deliberately respect the existing global safety switches:

```dotenv
AMAZON_SP_API_ENABLED=false
AMAZON_SP_API_CALLS_ENABLED=false
FLIPKART_SELLER_API_ENABLED=false
FLIPKART_SELLER_API_CALLS_ENABLED=false
```

Keep these disabled until the corresponding platform access is approved and the next read-only API integration package has been installed and tested. The providers throw `ConnectorDisabled` while calls are disabled. Enabling these flags does not enable marketplace writes; existing write gates remain in place.

Credentials are read from the encrypted `marketplace_connections.credentials` cast. Do not copy seller secrets into `.env`, logs, queue payloads, or browser responses.

## Important scope boundary

- Amazon token issuance proves LWA credential exchange only. It does not prove Orders, Listings, Inventory or other SP-API roles/operations are authorized.
- Flipkart token issuance proves the self-access application can obtain a token. It does not prove each Seller API operation is enabled for the seller.
- This package does **not** implement product/order/inventory API requests, SP-API/Flipkart synchronization, or marketplace write operations.
- No migration is required for this package.

## Rollback

Restore the previous versions of the changed files from your backup, remove the newly added files, and remove the route line added in step 3. No database rollback is needed.

## Validation note

PHP syntax checks passed in the build environment. This archive did not include `vendor/`, so PHPUnit could not be executed here. Run the commands above in your WAMP project and share the results before proceeding to the read-only synchronization package.
