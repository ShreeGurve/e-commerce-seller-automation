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
