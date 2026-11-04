# Seller Automation — Marketplace Operations Phase 1

## Included
- Internal marketplace operations dashboard with mapping counts, filters and pagination.
- Create/remove local product-to-marketplace SKU mappings.
- Navigation link in the Sales menu.
- Uses existing database tables; no migration required.

## Install
1. Back up your current project.
2. Extract this ZIP into the Laravel project root, preserving folder structure and overwrite the included files.
3. Run `php artisan optimize:clear`.
4. Run `php artisan route:list --name=marketplaces.operations`.
5. Open `/marketplace-operations` after login.

## Permissions
- View: `products.view`
- Add/remove mapping: `products.edit`

## Safety
This is internal/local catalogue management only. It does not call Amazon or Flipkart APIs, publish listings, change live prices or synchronize inventory. Seller account verification is not required.

No migrations are included. Existing `product_marketplace`, `products`, `marketplaces` and `listings` tables are used.


# Seller Automation — Marketplace Operations Phase 2

## Scope
This update extends the existing Listing Management module with an internal, version-specific review workflow.

### Included
- Additive `listing_reviews` table.
- Review request tied to the exact `listing_versions` record.
- Approve or request changes with reviewer notes.
- Review history on the listing detail page.
- Review status in the listing list.
- Approval gate: a version must be approved before it can be marked as live.
- New versions require their own review; older approvals do not approve later edits.
- No Amazon/Flipkart API calls and no marketplace publishing.

## Installation
1. Back up the project and database.
2. Extract this ZIP into the Laravel project root, preserving folders.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=listings
```

Do not run `migrate:fresh`.

## Workflow
1. Open Listings and create or edit a product/channel listing.
2. Save the content. Each save creates a new immutable version.
3. Open the listing and choose **Submit current version for review**.
4. A user with `listings.manage` can approve it or request changes with a note.
5. After approval, use **Mark live** only after manually applying that approved version on the marketplace.

## Permissions
The existing `listings.manage` permission is used for submitting and actioning reviews. No new permission records or seeder changes are required.

## Notes
- The migration is additive and creates only `listing_reviews`.
- Existing listing, listing_versions, product, marketplace and connection data are not modified.
- No API credentials are read or changed.
- Runtime migration and browser tests must be run in your local Laravel environment.
