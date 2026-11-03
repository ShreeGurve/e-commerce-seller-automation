# Seller Automation – Marketplace Integration Foundation

## Included in this update
- Marketplace Connections diagnostics page for Amazon SP-API and Flipkart Seller API.
- Readiness status distinguishes missing credentials from configured-but-not-yet-authorized credentials.
- Diagnostics never display credential values and do not call third-party APIs.
- Product-level channel pricing inputs: outbound shipping, handling, target profit range and optional manual selling price.
- New additive migration; no existing migration was edited or deleted.
- Marketplace API write switches remain disabled. No false "connected" or "published" state is generated.

## Not yet live / blocked by missing seller API access
- Amazon seller authorization, SP-API SigV4 request signing and live operation calls.
- Flipkart authenticated API calls and seller permission validation.
- Product create/update/pull/push, catalogue matching and live price/inventory publishing.
- Order API pull, accept/confirm, labels/invoices, pickup scheduling and dispatch updates.
- Settlement API pull and live reconciliation.

These need actual seller API credentials, account/marketplace details, and official API permission confirmation. After credentials are supplied through the server `.env`, the next integration pass should implement and test each platform operation against the seller account. Until then, existing CSV import remains available.

## Apply
```bash
php artisan migrate
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Validation performed
- PHP syntax check: 288 PHP files checked, zero syntax errors.
- Laravel/PHPUnit test suite: not run; this source ZIP did not include `vendor/`, and Composer was unavailable in the build environment.
- Database migration: not executed against a live database.
