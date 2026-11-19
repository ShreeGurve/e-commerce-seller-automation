# Package 18 — Marketplace Automation Rules & Approval Center

Creates reviewable tasks from Package 17 recommendations. It is an approval/task workflow, not an execution engine. No marketplace API calls, inventory writes, pricing changes, purchase orders, refunds, or listing edits are performed.

## Included

- Configurable enabled/disabled rules for RESTOCK, STOCK_RISK, SALES_DECLINE, HIGH_RETURN_RATE and LOW_PROFIT_MARGIN.
- Default rules inserted by the migration.
- Manual recommendation scan for 7/14/30/60/90-day windows.
- Idempotent task creation using a unique rule + signal + product + period fingerprint.
- Priority override and reviewer guidance.
- Task assignment-ready schema (`assigned_to`) for a later team assignment UI.
- Approval lifecycle: PENDING → APPROVED or REJECTED; APPROVED → COMPLETED.
- Reviewer, timestamp and note audit fields.
- Feature tests for defaults, rule creation/toggle, supported signal types and status transitions.

## Files

- `database/migrations/2026_11_19_000011_create_marketplace_automation_rules_tables.php`
- `app/Services/MarketplaceAutomationRuleService.php`
- `app/Http/Controllers/MarketplaceAutomationRuleController.php`
- `resources/views/automation/marketplace-rules.blade.php`
- `tests/Feature/MarketplaceAutomationRuleServiceTest.php`

## Install

1. Back up application and database.
2. Extract ZIP at Laravel project root.
3. Ensure Package 17 is installed (uses `MarketplaceRecommendationService`).
4. Add routes inside the authenticated route group:

```php
use App\Http\Controllers\MarketplaceAutomationRuleController;

Route::middleware('can:finance.view')->prefix('automation/marketplace-rules')->name('automation.marketplace-rules.')->group(function () {
    Route::get('/', [MarketplaceAutomationRuleController::class, 'index'])->name('index');
    Route::post('/rules', [MarketplaceAutomationRuleController::class, 'store'])->name('store');
    Route::patch('/rules/{rule}/toggle', [MarketplaceAutomationRuleController::class, 'toggle'])->name('toggle');
    Route::post('/generate', [MarketplaceAutomationRuleController::class, 'generate'])->name('generate');
    Route::post('/tasks/{task}/review', [MarketplaceAutomationRuleController::class, 'review'])->name('review');
});
```

5. Run against the normal development MySQL configuration:

```bash
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

6. Test with SQLite memory and array cache:

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
$env:CACHE_STORE="array"
php artisan test --filter=MarketplaceAutomationRuleServiceTest
php artisan test
```

7. Open `/automation/marketplace-rules`.

## Important behaviour and limitations

- Cooldown suppression prevents a new task for the same rule, signal and product while a task exists inside the configured cooldown window. The unique period fingerprint also prevents duplicate tasks for the same analysis window.
- `assigned_to` is schema-ready but assignment controls are not included in this package.
- APPROVED means the team approved the task/recommendation, not that an action was executed. COMPLETED is a manual status update only.
- This package does not call marketplace APIs and does not modify stock, prices, orders, returns, settlements or accounting data.
- The route uses `finance.view`; change it only if your permission model uses a different existing permission.

## Verification

PHP syntax and ZIP integrity checks are performed on the package archive. PHPUnit must be run inside the target Laravel project; do not treat tests as passed until the local test output confirms it.
