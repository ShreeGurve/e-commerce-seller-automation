# Seller Automation — Phase 3: Channel-wise Pricing Engine

## Scope
This is an additive refinement of the existing channel pricing engine. It preserves the existing Marketplace Operations routes and any Phase 2 listing review files in the user's local project.

## Included
- Channel-specific outbound shipping and handling remain included in every channel recommendation when configured.
- The `auto_price_enabled` control now has a clear, functional meaning: when enabled, the Pricing engine uses that channel's desired minimum and target profit per unit (₹).
- When disabled, the engine uses the global minimum and target margin percentages, while still including channel shipping and handling costs.
- Pricing detail page has a direct link to Product → Channel Pricing.
- Clear explanation on the pricing detail page showing which target mode is active.
- Feature test covering the disabled-target mode and confirming channel logistics costs still affect break-even.

## Safety
- This is an internal pricing recommendation only.
- No Amazon or Flipkart API calls are made.
- No marketplace price is published or changed.
- `push_enabled` remains forced off by the existing save logic.
- No database migration is included.
- No routes file is included, so the Marketplace Operations route fixes already applied locally are not overwritten.

## Install
1. Back up the project.
2. Extract this ZIP into the Laravel project root, preserving folder structure and replacing only the included files.
3. Run:

```bash
php artisan optimize:clear
```

4. Open a product → Channel Pricing. Configure shipping and handling for Amazon and/or Flipkart.
5. Enable **Use channel-specific profit targets** if you want the rupee-per-unit min/target amounts used. Leave it off to use global margin settings.
6. Open Pricing, select the marketplace, and inspect the recommendation.

## Validation
- PHP syntax checks passed for the changed PHP files.
- PHPUnit was not run here because the project archive does not include `vendor/`. If running locally, use a dedicated in-memory SQLite test database only:

```bash
php artisan test --filter=PricingTest
```

Do not run the RefreshDatabase-based tests against your live MySQL database. Do not run `migrate:fresh`.
