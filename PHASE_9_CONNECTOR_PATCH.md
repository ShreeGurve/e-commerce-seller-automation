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
