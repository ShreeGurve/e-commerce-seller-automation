# Package 28 — Marketplace Write Compliance & Retention Governance

## Scope
Adds business-scoped evidence retention policies, expiry dashboard, legal holds, archival, deletion request/independent approval workflow, and immutable-style audit events.

**Safety:** this package never physically deletes evidence. Approval changes the record to `DELETION_APPROVED`; actual purge is intentionally not implemented. Legal holds block deletion requests, approvals, and automatic archival.

## 1. Copy files
Copy the package files into the matching paths. This package adds a migration and does not replace Package 27 files.

## 2. Register controller import
Add once in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceWriteRetentionController;
```

## 3. Register routes
Add inside the existing authenticated route group:

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
```

## 4. Schedule archival
Add to `routes/console.php`:

```php
Schedule::command('marketplace-writes:evidence-archive-expired')
    ->dailyAt('01:20')
    ->withoutOverlapping();
```

Ensure Laravel's scheduler is running on your server.

## 5. Migrate and clear caches

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
php artisan route:list --path=marketplace-writes/retention
```

Do not use `migrate:fresh` on your working database.

## 6. Run checks

```powershell
$env:DB_CONNECTION="sqlite"
$env:DB_DATABASE=":memory:"
$env:CACHE_STORE="array"
php artisan test
php vendor/bin/pint --test app bootstrap/app.php bootstrap/providers.php config database routes tests
```

Remove temporary environment overrides afterwards if needed.

## 7. Open
`/marketplace-writes/retention`

## Important implementation notes
- Policies are business-specific.
- Existing evidence is not assigned an expiry retroactively when a policy is saved. This avoids inventing retention dates. New evidence records inherit the business policy's retention period at creation; when no policy exists, the default is 365 days.
- Automatic processing archives expired evidence only when there is no active legal hold.
- A deletion request requires a different user to approve it. Approval does not physically delete evidence.
- Hold release route is provided for authorized users; release requires a reason and is audited.
- Review role permissions before enabling this route group for non-admin users.
- MySQL migration and browser workflow should be verified in the target environment before production rollout.
