# Package 19 — Marketplace Automation Execution Engine (Controlled / Dry-Run)

## Purpose
Adds a controlled execution boundary for Package 18 approved automation tasks. This release implements **dry-run previews and execution audit history only**. It does not implement Amazon or Flipkart write APIs.

## Included files
- `database/migrations/2026_11_19_000012_create_marketplace_automation_execution_tables.php`
- `app/Services/MarketplaceAutomationExecutionService.php`
- `app/Http/Controllers/MarketplaceAutomationExecutionController.php`
- `resources/views/automation/marketplace-executions.blade.php`
- `tests/Feature/MarketplaceAutomationExecutionServiceTest.php`

## Prerequisites
Packages 17 and 18 must already be installed and migrated. Package 18 provides `marketplace_automation_tasks` and its approval workflow.

## Routes
Add inside the existing authenticated route group in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceAutomationExecutionController;

Route::middleware('can:finance.view')->prefix('automation/marketplace-executions')->name('automation.marketplace-executions.')->group(function () {
    Route::get('/', [MarketplaceAutomationExecutionController::class, 'index'])->name('index');
    Route::post('/tasks/{task}/dry-run', [MarketplaceAutomationExecutionController::class, 'dryRun'])
        ->middleware('can:orders.manage')->name('dry-run');
    Route::post('/tasks/{task}/live', [MarketplaceAutomationExecutionController::class, 'live'])
        ->middleware('can:orders.manage')->name('live');
});
```

The `live` endpoint intentionally records a `BLOCKED` execution attempt. The confirmation field is required to make the attempted action deliberate, but it does not override the fail-closed service guard. The UI does not expose a live action button.

If your permission catalog uses different names, replace `finance.view` and `orders.manage` with the equivalent read/manage permissions already used by your application. Do not remove authorization middleware.

## Installation
1. Back up the project and database.
2. Extract this ZIP at the Laravel project root, preserving paths.
3. Add the routes above.
4. Clear cached configuration/routes:

```powershell
php artisan optimize:clear
```

5. Preview and run the migration:

```powershell
php artisan migrate --pretend
php artisan migrate
```

6. Run focused and full tests (use the test environment settings below):

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
$env:CACHE_STORE="array"
php artisan test --filter=MarketplaceAutomationExecutionServiceTest
php artisan test
```

7. Visit `/automation/marketplace-executions`.

## Safety model
- Only tasks with status `APPROVED` can receive a successful dry-run preview.
- Every attempt is written to `marketplace_automation_executions` with mode, status, idempotency key, payload, validation result, user, timestamps and duration.
- Repeating the same idempotency key returns the original execution record and does not create another attempt.
- Live mode is **always blocked**, even if a policy row is edited to enable it. There is no marketplace write adapter in this package.
- Default policies enable dry runs and disable live mode for the five recommendation types.
- Dry runs do not change task status, stock, prices, listings, orders, returns, refunds, payouts or accounting balances.
- No API calls are made. No credentials or marketplace permissions are required.

## Not included / future prerequisites
Real live execution requires separately reviewed Amazon SP-API / Flipkart API write adapters, verified scopes and account permissions, per-connection operation allowlists, explicit live enablement, rate limits, payload validation, retry policy, rollback/compensation design and production audit controls. This package deliberately does not claim those integrations exist.
