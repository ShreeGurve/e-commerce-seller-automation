# Seller Automation — Package 13: Marketplace Returns, Refunds & Claims Management

## What this package adds

- Staging and deduplication for marketplace return records, keyed by marketplace + external return ID.
- Manual review before an imported return is created in the existing `returns` / `return_items` ledger.
- Exact order-line matching by external line ID, with marketplace SKU as fallback.
- Validation preventing total returned quantity from exceeding the ordered quantity across prior returns.
- Return inspection snapshots with received quantity, condition, disposition and reviewer.
- Claim-eligibility flagging for missing, damaged, used, scrapped or non-restocked units.
- Staged marketplace refund records and manual reconciliation into the existing `RefundService` / `refunds` ledger.
- Audit events for return import and inspection.
- Dashboard at `/marketplaces/returns`.

## Reuse of existing project modules

This package intentionally extends the existing `ProductReturn`, `ReturnItem`, `Refund`, `RefundService`, and `ClaimService` workflows. It does not create a second returns/refunds/claims ledger. The existing `ClaimService::suggestFromReturn()` can use the inspected return and `claim_eligible` flag to prepare a claim draft. Claim submission, approval, documents and deadlines remain controlled by the existing claims module.

## Safety and scope

- This is an import/review/reconciliation foundation. It does **not** poll Amazon or Flipkart for return/refund data. The staging methods accept records supplied by an authorized CSV/API import process.
- Importing a return requires an existing matching order and matching order lines.
- Refund reconciliation calls the existing `RefundService`, so the existing shipped-order and maximum-refundable-amount validations remain in force.
- Only INR refund imports are accepted because the current project refund ledger is INR-based.
- Inspection disposition is recorded for operations and claim evidence. `restock_qty` is informational only in this package: **no inventory quantity is changed**. A separately approved stock workflow must handle any physical restock.
- No marketplace API write, refund initiation, return authorization, claim submission or seller-portal action is performed.
- Evidence files remain under the existing private return/claim storage workflow; this package stores inspection notes and structured metadata only.

## Installation

1. Back up the application and database.
2. Extract the ZIP into the Laravel project root. Archive paths begin with `app/`, `database/`, `resources/`, and `tests/`.
3. Add the routes inside the existing authenticated route group in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceReturnsController;

Route::middleware('can:orders.view')->group(function () {
    Route::get('/marketplaces/returns', [MarketplaceReturnsController::class, 'index'])
        ->name('marketplaces.returns.index');
});

Route::middleware('can:orders.manage')->group(function () {
    Route::post('/marketplaces/returns/stage', [MarketplaceReturnsController::class, 'stageReturn'])
        ->name('marketplaces.returns.stage');
    Route::post('/marketplaces/returns/{import}/approve', [MarketplaceReturnsController::class, 'approveReturn'])
        ->name('marketplaces.returns.approve');
    Route::post('/marketplaces/returns/{import}/reject', [MarketplaceReturnsController::class, 'rejectReturn'])
        ->name('marketplaces.returns.reject');
    Route::post('/marketplaces/returns/{return}/inspect', [MarketplaceReturnsController::class, 'inspect'])
        ->name('marketplaces.returns.inspect');
    Route::post('/marketplaces/returns/refunds/stage', [MarketplaceReturnsController::class, 'stageRefund'])
        ->name('marketplaces.returns.refunds.stage');
    Route::post('/marketplaces/returns/refunds/{import}/reconcile', [MarketplaceReturnsController::class, 'reconcileRefund'])
        ->name('marketplaces.returns.refunds.reconcile');
});
```

4. Clear caches and preview migrations:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
```

5. Run migration:

```powershell
php artisan migrate
```

6. Run focused and full tests:

```powershell
php artisan test --filter=MarketplaceReturnsServiceTest
php artisan test
```

7. Open `/marketplaces/returns`. Users need the existing `orders.view` and `orders.manage` permissions.

## Workflow

1. Stage a return from a trusted marketplace export/API integration. Supply the external order/return IDs and one or more order line references.
2. Review and import it. The service links it to the existing order and prevents over-return quantities.
3. Inspect every line. Record received quantity, condition and proposed disposition. Claim eligibility is flagged where units are missing or not fully restockable.
4. Use the existing claims module to prepare a draft from an eligible return; claim submission and evidence handling remain manual and permission-controlled.
5. Stage marketplace refund records and reconcile each one manually. The existing `RefundService` validates order status, duplicate external refund IDs and refundable balance.

## Files added

- `app/Models/MarketplaceReturnImport.php`
- `app/Models/MarketplaceRefundImport.php`
- `app/Models/ReturnInspection.php`
- `app/Models/MarketplaceReturnEvent.php`
- `app/Services/MarketplaceReturnsService.php`
- `app/Http/Controllers/MarketplaceReturnsController.php`
- `database/migrations/2026_11_19_000010_create_marketplace_return_management_tables.php`
- `resources/views/marketplaces/returns/index.blade.php`
- `tests/Feature/MarketplaceReturnsServiceTest.php`

## Rollback

Remove the routes first. Roll back this migration only if it is the latest migration:

```powershell
php artisan migrate:rollback --step=1
```

Otherwise use your controlled migration rollback procedure. This migration only drops the four tables introduced by Package 13; it does not modify the existing returns, return_items, refunds or claims tables.
