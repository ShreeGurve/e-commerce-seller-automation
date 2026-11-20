# Package 21 — Marketplace Write Submission Verification & Reconciliation

## Purpose

Package 20 records whether Amazon or Flipkart accepted a write request. An API-level `ACCEPTED` response is not proof that the requested listing change is visible to customers. Package 21 adds a manual, business-scoped verification workflow so an operator can check Seller Central / Flipkart Seller Hub and record the observed outcome.

## Included files

- `database/migrations/2026_11_19_000014_create_marketplace_write_verification_tables.php`
- `app/Services/MarketplaceWriteVerificationService.php`
- `app/Http/Controllers/MarketplaceWriteVerificationController.php`
- `resources/views/marketplaces/write-verification.blade.php`
- `resources/views/marketplaces/write-verification-history.blade.php`
- `tests/Feature/MarketplaceWriteVerificationServiceTest.php`

## Routes

Add inside the authenticated routes section of `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceWriteVerificationController;

Route::middleware(['auth', 'can:listings.manage'])
    ->prefix('marketplace-writes/verification')
    ->name('marketplaces.writes.verification.')
    ->group(function () {
        Route::get('/', [MarketplaceWriteVerificationController::class, 'index'])->name('index');
        Route::get('/{attempt}/history', [MarketplaceWriteVerificationController::class, 'history'])->name('history');
        Route::post('/{attempt}', [MarketplaceWriteVerificationController::class, 'store'])->name('store');
    });
```

Open `/marketplace-writes/verification`. The dashboard links back to the Package 20 Write Control Center at `/marketplace-writes`.

## Outcomes

- `PENDING` — the marketplace is still processing the submission.
- `LIVE_CONFIRMED` — the operator checked the seller portal and confirmed the requested change is visible. This is final for that attempt.
- `NOT_VISIBLE` — the requested change could not be found at the time of checking.
- `ACTION_REQUIRED` — the marketplace reports an issue that needs operator action.
- `REJECTED` — the marketplace rejected the requested change.

A later check may update any non-final outcome. Each check appends a separate event containing the previous and new status, note, issue code, evidence link and recording user. A `LIVE_CONFIRMED` record cannot be overwritten; a later change must be a new reviewed write attempt.

## Safety

- This is a manual verification workflow. It makes no Amazon or Flipkart API calls.
- It never retries, resubmits, compensates, or changes the original write attempt status.
- Only attempts with `ACCEPTED` or `REVIEW_REQUIRED` submission status can be verified.
- The user who submitted the marketplace write cannot certify its outcome.
- Records and history are scoped to the user's business.
- Evidence links must use HTTPS. Do not paste access tokens, refresh tokens, passwords, or private customer data into notes or links.
- This package does not claim that a seller-portal check is a live API verification; the note records the operator's observed evidence.

## Installation

1. Back up the application and database.
2. Extract the ZIP into the Laravel project root.
3. Add the routes above (do not duplicate an existing route group).
4. Run migrations against the normal MySQL development database:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

5. Run tests with isolated SQLite memory and array cache:

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
$env:CACHE_STORE="array"
php artisan test --filter=MarketplaceWriteVerificationServiceTest
php artisan test
```

6. Open `/marketplace-writes/verification` as a user with `listings.manage` permission.

## Rollback

Only if you need to remove this feature and have backed up its data:

```powershell
php artisan migrate:rollback --step=1
```

Check the latest migration order first. If other migrations were applied after Package 21, roll back only the Package 21 migration by the project's normal controlled migration procedure; do not blindly roll back unrelated migrations.

## Validation

PHP syntax and ZIP integrity are checked during package preparation. PHPUnit must be run in the target Laravel installation; no test pass is claimed until the local output confirms it.
