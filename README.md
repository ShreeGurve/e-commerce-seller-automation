# Seller Automation — Package 14
## Marketplace Bank Payout Reconciliation

This package adds a controlled workflow to compare the marketplace settlement's net amount with the actual bank credit received.

### What is included
- Bank payout records with unique marketplace + bank reference protection.
- Optional direct link to an existing marketplace settlement, automatic lookup by marketplace + external settlement ID, or save as unmatched and link later.
- Automatic amount comparison: absolute difference up to ₹1.00 is `MATCHED`; greater difference is `MISMATCH`.
- Marketplace ownership validation, duplicate settlement-link prevention, review notes, ignore/reopen workflow, and reviewer audit fields.
- Finance dashboard at `/finance/payout-reconciliation` with status counts, filters, and bank-vs-settlement comparison.

### Important scope and controls
- This is a manual bank-credit recording/reconciliation workflow. It does not connect to a bank API, import bank statements, initiate marketplace payouts, or change accounting balances.
- Current matching model is one bank credit to one settlement. Split payouts and one bank credit covering multiple settlements are deliberately rejected/not supported yet; do not force-link such cases.
- INR only in this UI. The ₹1.00 tolerance is fixed in this package.
- Existing settlement lines, order reconciliation, refunds, claims, and marketplace credentials are not modified.

### Files
- `database/migrations/2026_11_19_000011_create_marketplace_bank_payout_reconciliations_table.php`
- `app/Models/MarketplaceBankPayout.php`
- `app/Models/Settlement.php` — replace the existing model with this version; it adds the `bankPayout()` relation.
- `app/Services/MarketplacePayoutReconciliationService.php`
- `app/Http/Controllers/MarketplacePayoutReconciliationController.php`
- `resources/views/finance/payout-reconciliation/index.blade.php`
- `tests/Feature/MarketplacePayoutReconciliationTest.php`

### Routes to add
Add these inside the existing authenticated `Route::middleware('auth')->group(...)` in `routes/web.php`:

```php
Route::get('/finance/payout-reconciliation', [\App\Http\Controllers\MarketplacePayoutReconciliationController::class, 'index'])
    ->middleware('can:finance.view')->name('finance.payout-reconciliation.index');

Route::middleware('can:finance.manage')->prefix('finance/payout-reconciliation')->name('finance.payout-reconciliation.')->group(function () {
    Route::post('/', [\App\Http\Controllers\MarketplacePayoutReconciliationController::class, 'store'])->name('store');
    Route::post('/{payout}/link', [\App\Http\Controllers\MarketplacePayoutReconciliationController::class, 'link'])->name('link');
    Route::post('/{payout}/ignore', [\App\Http\Controllers\MarketplacePayoutReconciliationController::class, 'ignore'])->name('ignore');
    Route::post('/{payout}/reopen', [\App\Http\Controllers\MarketplacePayoutReconciliationController::class, 'reopen'])->name('reopen');
});
```

### Installation
1. Back up the project and database.
2. Extract this ZIP at the Laravel project root. Archive paths start with `app/`, `database/`, `resources/`, and `tests/`.
3. Add the routes above.
4. Replace `app/Models/Settlement.php` with the package version (the only change is the new `bankPayout()` relationship).
5. Run `php artisan optimize:clear`.
6. Review migration SQL with `php artisan migrate --pretend`.
7. Run `php artisan migrate`.
8. Run targeted tests with safe test environment:
   ```powershell
   $env:DB_CONNECTION="sqlite"
   $env:DB_DATABASE=":memory:"
   php artisan test --filter=MarketplacePayoutReconciliationTest
   php artisan test
   ```
9. Open `/finance/payout-reconciliation` using a user with `finance.view`; record/link/ignore actions require `finance.manage`.

### Operational notes
- Bank reference must be unique per marketplace.
- A settlement can only be linked to one payout row in this workflow.
- A mismatch is not auto-resolved or written into the ledger. Investigate the bank statement and marketplace settlement report, then retain a clear review note.
