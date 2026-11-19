# Seller Automation — Package 11: Marketplace Attribute & Category Mapping

## What this package adds

- Configurable attribute profiles per marketplace and category key.
- Attribute definitions with required/optional flags, text/number/boolean/select/multi-select types, allowed values, length limits, and variant-attribute markers.
- Listing-version-specific mappings from a controlled list of internal product/listing fields or manually entered values.
- Bulk save of multiple attribute mappings for a listing.
- Validation audits for missing required values, invalid types/options/lengths, unapproved mappings, and missing variant values on active product variants.
- Two-person review: the user who created a mapping cannot approve or reject their own mapping.
- Validation history and a mapping workspace.

## Scope and marketplace safety

- Profiles are **MANUAL** configuration. This package does not include official Amazon or Flipkart category schemas and does not claim that configured definitions match current marketplace requirements.
- Obtain category schemas from authorized marketplace documentation or seller tools, record the schema version/reference, and maintain them when marketplaces change their requirements.
- `is_variant_attribute` checks that active product variants carry the configured attribute key. It does not create parent/child marketplace listings or prove that a marketplace accepts the variation theme.
- Source-field mapping is limited to an explicit allowlist. No arbitrary PHP paths or expressions are evaluated.
- Saving a mapping sets it to `PENDING`. Approval is separate. A validation audit flags mappings that are not approved.
- This package does not change listing content, create marketplace listings, or publish/sync attribute updates. Marketplace write APIs remain out of scope and disabled.

## Installation

1. Back up the application and database.
2. Extract the ZIP into the Laravel project root. Archive paths begin with `app/`, `database/`, `resources/`, and `tests/`.
3. Add these routes inside the existing authenticated route group in `routes/web.php`:

```php
use App\Http\Controllers\MarketplaceAttributeMappingController;

Route::middleware('can:listings.view')->group(function () {
    Route::get('/listings/attributes', [MarketplaceAttributeMappingController::class, 'index'])
        ->name('listings.attributes.index');
    Route::get('/listings/{listing}/attributes', [MarketplaceAttributeMappingController::class, 'workspace'])
        ->name('listings.attributes.workspace');
});

Route::middleware('can:listings.manage')->group(function () {
    Route::post('/listings/attributes/profiles', [MarketplaceAttributeMappingController::class, 'storeProfile'])
        ->name('listings.attributes.profiles.store');
    Route::post('/listings/{listing}/attributes', [MarketplaceAttributeMappingController::class, 'save'])
        ->name('listings.attributes.save');
    Route::post('/listings/{listing}/attributes/validate', [MarketplaceAttributeMappingController::class, 'validateListing'])
        ->name('listings.attributes.validate');
    Route::post('/listing-attribute-mappings/{mapping}/review', [MarketplaceAttributeMappingController::class, 'review'])
        ->name('listings.attributes.review');
});
```

4. Run migration preview, migrate, and tests:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
php artisan test --filter=MarketplaceAttributeMappingServiceTest
php artisan test
```

5. Open `/listings/attributes`. Ensure user roles have the `listings.view` and `listings.manage` permissions. The migration adds `listings.attributes.view` and `listings.attributes.manage` as granular permission records if the permissions table exists; the supplied routes use the established `listings.view` / `listings.manage` gates for compatibility with the existing listing module.

## Workflow

1. Create a marketplace/category profile using a category key that exactly matches `listings.category_path`.
2. Define required and optional attributes. Add allowed values for `SELECT` and `MULTI_SELECT` fields.
3. Open **Map attributes** for a listing and save source-field mappings or manual values.
4. A different authorized user reviews each pending mapping.
5. Run validation and review the persisted audit result. Update mappings and revalidate as needed.

## Files added

- `app/Models/MarketplaceAttributeProfile.php`
- `app/Models/MarketplaceAttributeDefinition.php`
- `app/Models/ListingAttributeMapping.php`
- `app/Models/ListingAttributeMappingAudit.php`
- `app/Services/MarketplaceAttributeMappingService.php`
- `app/Http/Controllers/MarketplaceAttributeMappingController.php`
- `database/migrations/2026_11_19_000008_create_marketplace_attribute_mapping_tables.php`
- `resources/views/listings/attribute-mapping.blade.php`
- `resources/views/listings/attribute-mapping-workspace.blade.php`
- `tests/Feature/MarketplaceAttributeMappingServiceTest.php`

## Rollback

Remove the routes, then roll back this migration only if it is the latest migration:

```powershell
php artisan migrate:rollback --step=1
```

Otherwise use your normal controlled migration rollback procedure. Permission records are intentionally not deleted by rollback because they may already be assigned or customized.
