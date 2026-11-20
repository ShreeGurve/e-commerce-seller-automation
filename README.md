# Package 27 — Marketplace Write Evidence Vault

Stores business-scoped evidence metadata for marketplace write operations, approvals, incidents and recovery work.

## Included
- Evidence metadata migration
- Business-scoped service and controller
- Evidence vault list, filters and create form
- HTTPS-only evidence URL policy
- Pagination

## Dependencies
- Existing authenticated user model with `business_id`
- Existing `layouts.app` Blade layout
- Bootstrap-compatible styling
- No dependency on Package 23–26 database tables; subject references are intentionally polymorphic metadata and are not foreign keys.

## Important security note
This package stores evidence links and descriptions, not uploaded file bytes. Store files in a private object store and provide short-lived HTTPS links. Do not expose sensitive marketplace credentials or customer personal data in evidence descriptions.

## Migration
Run `php artisan migrate --pretend` and inspect the SQL, then `php artisan migrate`.

## Routes
Add the exact route group from `INSTALL-PACKAGE-27.md`.

## Validation
PHP syntax and ZIP integrity are checked during packaging. Run migrations and feature tests in the target application before production use.
