# Seller Automation — Combined Package 28 + Package 29

Baseline reviewed: `seller-automation-audit(1).zip` (uploaded 21 Nov 2026). It already contains Package 27 Evidence Vault and Packages 23–26 controllers/routes/migrations. It does not contain Package 28 retention files. This delta ZIP includes Package 28 plus Package 29.

## Contents
- Package 28: evidence retention policy, legal holds, archive-expiry command, independent deletion approval workflow (no physical deletion).
- Package 29: read-only compliance scan, finding history, business-scoped dashboard and CSV export.

## Installation order
1. Extract this ZIP into the Laravel project root and overwrite only when prompted for the included new files. Package 28 adds the retention service/controller/command/view and migration.
2. Add the route imports and route groups below to `routes/web.php` (inside the existing authenticated route area). The baseline already imports `MarketplaceWriteEvidenceController`; do not duplicate that import.
3. Add the scheduler entries below to `routes/console.php`. Keep the existing `Schedule` import if present.
4. Run migrations and clear route/config caches.

## `routes/web.php` imports
```php
use App\Http\Controllers\MarketplaceWriteRetentionController;
use App\Http\Controllers\MarketplaceWriteComplianceController;
```

## `routes/web.php` route groups
```php
Route::middleware(['auth', 'can:listings.manage'])
    ->prefix('marketplace-writes/retention')
    ->name('marketplaces.writes.retention.')
    ->group(function () {
        Route::get('/', [MarketplaceWriteRetentionController::class, 'index'])->name('index');
        Route::post('/policy', [MarketplaceWriteRetentionController::class, 'savePolicy'])->name('policy');
        Route::post('/evidence/{evidence}/hold', [MarketplaceWriteRetentionController::class, 'placeHold'])->whereNumber('evidence')->name('hold');
        Route::post('/holds/{hold}/release', [MarketplaceWriteRetentionController::class, 'releaseHold'])->whereNumber('hold')->name('release');
        Route::post('/evidence/{evidence}/request-deletion', [MarketplaceWriteRetentionController::class, 'requestDeletion'])->whereNumber('evidence')->name('request-deletion');
        Route::post('/evidence/{evidence}/approve-deletion', [MarketplaceWriteRetentionController::class, 'approveDeletion'])->whereNumber('evidence')->name('approve');
    });

Route::middleware(['auth', 'can:listings.manage'])
    ->prefix('marketplace-writes/compliance')
    ->name('marketplaces.writes.compliance.')
    ->group(function () {
        Route::get('/', [MarketplaceWriteComplianceController::class, 'index'])->name('index');
        Route::post('/run', [MarketplaceWriteComplianceController::class, 'run'])->name('run');
        Route::get('/export', [MarketplaceWriteComplianceController::class, 'export'])->name('export');
    });
```

If your route file already has a surrounding `Route::middleware('auth')->group(...)`, the above is still valid; Laravel will apply both middleware layers. Do not add duplicate imports.

## `routes/console.php` scheduler
```php
Schedule::command('marketplace-writes:evidence-archive-expired')
    ->dailyAt('01:20')
    ->withoutOverlapping();

Schedule::command('marketplace-writes:compliance-scan')
    ->dailyAt('02:00')
    ->withoutOverlapping();
```

## Commands (PowerShell)
```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
php artisan route:list --path=marketplace-writes/retention
php artisan route:list --path=marketplace-writes/compliance
php artisan marketplace-writes:compliance-scan
php artisan schedule:list
```

Run `php artisan migrate` only after backing up your database. Do not use `migrate:fresh` on your project database.

## URLs
- Retention: `/marketplace-writes/retention`
- Compliance: `/marketplace-writes/compliance`

## What Package 29 checks
- Evidence records without an HTTPS evidence URL or reference code.
- Active evidence records without a retention expiry date.
- Evidence approaching its configured expiry window.
- Evidence awaiting independent deletion approval.
- Presence of active legal holds requiring periodic review.

Findings are saved per scan and per business. The dashboard and CSV export are scoped to the signed-in user's `business_id`.

## Safety and limitations
- Package 29 is a monitoring/reporting layer only. It does not block marketplace writes, change listing data, release legal holds, approve deletion, or physically delete evidence.
- Package 28's archive operation respects active legal holds. Deletion approval is a recorded governance state; physical purge remains a separate controlled process.
- The uploaded baseline contains two migrations with the same timestamp prefix `2026_11_20_000019` (recovery and evidence). Do not rename deployed migrations blindly; confirm the `migrations` table and migration status first.
- The full project ZIP does not include `vendor/`, so Laravel boot, PHPUnit, migration execution, MySQL compatibility and browser tests cannot be certified from static packaging alone. Validate in your local environment.
