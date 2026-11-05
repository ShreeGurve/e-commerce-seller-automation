# Seller Automation — Continued Phase 1–44 Audit

## Additional issues found and corrected

### 1. Duplicate named route: supplier invoice payment listing
The uploaded project registered both the supplier invoice payment listing and the finance supplier payment planning listing as `supplier-payments.index`. This could cause named-route resolution to select the wrong endpoint.

**Correction:** the invoice-payment listing is now named `supplier-invoice-payments.index`; the finance planning page retains `supplier-payments.index`. The invoice payment POST route remains `supplier-payments.store` and is unchanged.

### 2. Marketplace Operations routes had no matching controller methods
The project had routes and a Blade page for `MarketplaceController::operations`, `storeMapping`, and `destroyMapping`, but the controller only implemented `show()`.

**Correction:** added all three methods using the existing `product_marketplace`, `products`, and `marketplaces` schema and existing `ProductMarketplace` model. Search and filters, mapping creation, and mapping deletion are supported. This is local catalogue mapping only; no marketplace API write or sync is performed.

### 3. Supplier invoice payment screen overstated invoice balance after credit notes
The payment creation action already deducted applied credit notes when validating a new payment, but the payment listing screen only subtracted recorded payments when displaying the remaining balance. That could show an incorrect amount due after a credit note was applied.

**Correction:** eager-load credit applications and deduct applied credits from the displayed remaining balance. The page now displays paid amount, credits, and balance separately.

## Validation
- Full uploaded project PHP syntax scan: 418 PHP files, zero syntax errors.
- Corrected PHP files independently linted successfully.
- Archive contents reviewed for the Phase 30–44 migration families and route families.
- No migration files changed in this correction.
- ZIP integrity verified after packaging.

## Still not verified
The archive has no `vendor/` directory. Laravel Artisan route resolution, database migrations, PHPUnit, live database queries, and browser workflows could not be executed here. PHP syntax checks do not prove runtime correctness. Apply the patch to a backed-up local copy and verify using the commands below.

## Apply
1. Back up your project and database.
2. Extract this ZIP into the Laravel project root, replacing the four included files.
3. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=supplier-invoice-payments
php artisan route:list --name=supplier-payments
php artisan route:list --name=marketplaces.operations
```

4. Verify these screens and actions:
   - `/finance/supplier-payments` — existing finance payment planning page.
   - `/purchases/supplier-payments` — supplier invoice payment register.
   - Apply a supplier credit note, then confirm the invoice payment page displays the credit and reduced balance.
   - `/marketplace-operations` — search/filter mappings, add a test local mapping, and remove it.

## Security reminder
The uploaded source archive contains a `.env` file. This patch does not include it or expose its values. Exclude `.env` from future ZIP uploads and rotate active credentials if the archive was shared beyond a trusted/private environment.
