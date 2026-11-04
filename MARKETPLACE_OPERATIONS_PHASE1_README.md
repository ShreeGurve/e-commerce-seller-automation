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
