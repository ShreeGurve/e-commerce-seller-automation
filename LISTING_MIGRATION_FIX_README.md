# Seller Automation — Listing migration conflict fix

## What was wrong

The two migrations dated `2026_10_15_000001` both created `listings`, `listing_versions`, `keywords` and `keyword_rankings`. The `listing_intelligence` migration also created `price_history`, but that table is already owned by `2026_10_16_000001_create_pricing_tables.php`.

## Files in this patch

- `database/migrations/2026_10_15_000001_create_listing_intelligence_tables.php` — converted to an empty compatibility migration, retaining its filename so Laravel migration history is not broken.
- `database/migrations/2026_10_15_000001_create_listing_tables.php` — canonical schema used by the current Listing, ListingVersion, Keyword and KeywordRanking models/services. Includes `external_listing_id` from the former intelligence migration.
- `app/Models/Listing.php` — adds `external_listing_id` to fillable fields.

`price_history` is intentionally not created here; it remains in the existing pricing migration dated `2026_10_16`.

## IMPORTANT — check migration history before running

Take a database backup first. Then run:

```bash
php artisan migrate:status
```

### If BOTH `2026_10_15_000001` migrations show Pending

Replace the two migration files with the files in this patch, then run:

```bash
php artisan migrate
```

### If `create_listing_intelligence_tables` already shows Ran

Do not blindly run `php artisan migrate` with this patch. The old migration may already have created the legacy table structures (and possibly `price_history`). The canonical migration in this patch is designed for a clean install, not an automatic conversion of those legacy tables. Share the `migrate:status` output and the exact database error so a data-preserving repair migration can be prepared for that database state.

Do not use `migrate:fresh` on a database containing data; it drops all tables.

## Validation

The PHP migration files and updated model were syntax-checked. A real Laravel migration/database test requires the project's installed Composer dependencies and a test database; it has not been claimed as executed here.
