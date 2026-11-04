# Seller Automation – Channel Pricing Engine Update

## What changed
- Pricing analysis now includes the product's marketplace-specific outbound shipping and handling costs.
- When channel settings exist, minimum and target selling prices are calculated from the configured absolute profit-per-unit targets (₹), rather than the global margin percentages.
- The Pricing detail page shows the marketplace fee, outbound shipping, and handling components separately.
- A channel manual selling price is used as the analysis price when there is no recorded channel price; an explicitly recorded price always takes precedence.
- Pricing list queries eager-load the selected marketplace settings to avoid one settings query per product.
- Added feature tests for channel manual price precedence and channel cost / profit-target calculations.

## Safety
- This is an internal pricing recommendation only.
- No Amazon or Flipkart API calls are made.
- No price is published or changed on a seller account.
- Marketplace push remains disabled.

## Apply
1. Back up the current project.
2. Extract this ZIP into the Laravel project root, preserving folders and allowing these listed files to be replaced.
3. No new migration is included in this update. The earlier `2026_11_03_000001_create_marketplace_product_settings.php` migration must already have been applied for channel settings to exist.
4. Run:

```bash
php artisan optimize:clear
```

5. Open a product → Channel Pricing, save its marketplace shipping, handling and desired profit range, then open Pricing → choose the same marketplace to see the updated recommendation.

## Validation
- PHP syntax checks passed for the changed PHP files.
- PHPUnit could not be run in the build environment because `vendor/` is not present. Run the test locally with a dedicated in-memory SQLite test database only:

```bash
php artisan test --filter=PricingTest
```

Do not run tests against the live MySQL database; the existing test suite uses `RefreshDatabase`.
