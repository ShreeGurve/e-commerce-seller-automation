# Seller Automation — Package 12: Marketplace Listing Compliance & Suppression Management

## Included

- A structured issue register linked to a listing and optional listing version.
- Manual or imported issue source, external issue reference, issue code, category, severity, detection date and due date.
- Suppression / at-risk flag, evidence references, and resolution notes.
- Issue lifecycle: `OPEN`, `IN_PROGRESS`, `RESOLVED`, `DISMISSED`.
- Immutable event history for issue reporting and every status transition.
- Manual recheck snapshots with open-issue and suppression counts.
- Dashboard filters and counts for open, suppression, critical/blocker and resolved issues.

## Important scope and marketplace safety

- This package does **not** connect to Amazon or Flipkart to poll account health, listing suppression, policy notices, or case status. Issues are entered manually or imported by a separately authorized workflow.
- The **Recheck** service only snapshots the local issue register. It does not claim that a marketplace listing is currently compliant or live.
- No listing status is changed automatically. In particular, recording a suppression issue does not change `listings.status`.
- Evidence is stored as safe reference metadata (`label`, `reference`, optional `note`), not as uploaded files. Store actual documents in your existing controlled document storage and pass a reference/path only if that storage is access-controlled.
- No marketplace API write or appeal submission is included.

## Installation

1. Back up the project and database.
2. Extract this ZIP into the Laravel project root. Archive paths begin with `app/`, `database/`, `resources/`, and `tests/`.
3. Add the routes inside your existing authenticated route group in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceListingComplianceController;

Route::middleware('can:listings.view')->group(function () {
    Route::get('/listings/compliance', [MarketplaceListingComplianceController::class, 'index'])
        ->name('listings.compliance.index');
});

Route::middleware('can:listings.manage')->group(function () {
    Route::post('/listings/compliance/issues', [MarketplaceListingComplianceController::class, 'store'])
        ->name('listings.compliance.store');
    Route::patch('/listings/compliance/issues/{issue}', [MarketplaceListingComplianceController::class, 'update'])
        ->name('listings.compliance.update');
    Route::post('/listings/{listing}/compliance/recheck', [MarketplaceListingComplianceController::class, 'recheck'])
        ->name('listings.compliance.recheck');
});
```

4. Preview and run migration:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

5. Run tests:

```powershell
php artisan test --filter=MarketplaceListingComplianceServiceTest
php artisan test
```

6. Open `/listings/compliance`. Ensure the roles that should use this screen already have `listings.view` and `listings.manage` permissions.

## Workflow

1. Record a notice or suppression issue against the matching listing.
2. Enter the marketplace issue code, severity, whether it is a suppression, and any evidence references.
3. Move the issue to `IN_PROGRESS` while it is being investigated.
4. Resolve it only after entering resolution notes, or dismiss it with an audit note.
5. Use local recheck to snapshot outstanding issue counts. Obtain actual live marketplace status from the authorized seller portal/API separately.

## Files added

- `app/Models/MarketplaceListingIssue.php`
- `app/Models/MarketplaceListingIssueEvent.php`
- `app/Models/MarketplaceListingComplianceCheck.php`
- `app/Services/MarketplaceListingComplianceService.php`
- `app/Http/Controllers/MarketplaceListingComplianceController.php`
- `database/migrations/2026_11_19_000009_create_marketplace_listing_compliance_tables.php`
- `resources/views/listings/compliance.blade.php`
- `tests/Feature/MarketplaceListingComplianceServiceTest.php`

## Rollback

Remove the routes first. Roll back this migration only if it is the latest migration:

```powershell
php artisan migrate:rollback --step=1
```

Otherwise use your normal controlled migration rollback process.
