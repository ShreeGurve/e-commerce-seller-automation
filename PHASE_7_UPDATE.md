# Update 13 — Phase 7: Listings, Keywords & Pricing Intelligence

This update continues from the supplied Update 12 / Phase 6 archive. It adds working Laravel modules for listing management, listing quality scoring, immutable version snapshots, keyword tracking, and a pricing calculator/history. Existing modules and data are preserved.

## Added
- `database/migrations/2026_10_15_000001_create_listing_intelligence_tables.php`
- Models: `Listing`, `ListingVersion`, `Keyword`, `KeywordRanking`, `PriceHistory`
- Controllers: `ListingController`, `KeywordController`, `PricingController`
- Service: `PricingIntelligenceService`
- Views: `resources/views/listings/*`, `resources/views/keywords/index.blade.php`, `resources/views/pricing/index.blade.php`
- Feature/unit-level tests in `tests/Feature/ListingIntelligenceTest.php`

## Main features
- Create/edit listings linked to a product and marketplace; keep each saved version as a snapshot.
- Listing score out of 100 with a visible breakdown for title, bullets, description, backend keywords, images, and A+ content. Score is a content-completeness heuristic, not a marketplace ranking guarantee.
- Store product and marketplace keywords, type (primary, secondary, long-tail, backend, AEO, SEO, GEO), volume, relevance, and current rank.
- Pricing calculator uses current COGS + packaging cost history, other unit cost, marketplace fee %, ad cost %, and target margin %. It calculates break-even price, target price, and estimated profit. Saving adds a history record and updates the product's target selling price.
- New permission names: `keywords.view/manage`, `pricing.view/manage`. The existing `listings.view/manage` permissions are reused.

## Safe installation (Windows / WAMP)
1. Back up the project folder and database.
2. Extract this archive over a copy of your current project, keeping the same root folder structure. Do not replace your `.env` with `.env.example`.
3. In the project folder run:
   ```bat
   php artisan migrate
   php artisan optimize:clear
   ```
   The migration is additive; **do not run `migrate:fresh`**. It adds the Phase 7 permissions and grants them to existing Super Admin, Admin, and Marketing Manager roles without replacing customized role assignments. If your roles use different slugs, grant the permissions from Roles & Permissions to the relevant roles.
4. Sign in with a role that has `listings.view`, `keywords.view`, and `pricing.view`. Super Admin/Admin roles receive these after the permission seeder runs.
5. Open Listings, Keywords, and Pricing intelligence from the sidebar.

## Verification
- Create a listing, save it, edit it, and verify version history increments.
- Check listing score and breakdown; fill content to improve the score.
- Add and delete a keyword.
- Choose a product in Pricing intelligence, calculate a target, then save and verify price history and the product target price.
- Run `php artisan test` where the local PHP extensions and database test configuration are installed.

## Pricing assumptions / limits
- Fee and advertising rates are user-entered assumptions; this update does not fetch live marketplace fee tables or automatically publish changes to Amazon/Flipkart.
- Formula: retained share = 1 − marketplace fee rate − ad cost rate. Break-even = unit cost / retained share. Target price = unit cost / (retained share − target margin rate). If the denominator is zero or negative, the calculator returns zero for that price rather than dividing by zero.
- GST/tax handling, shipping/weight slabs, returns, and category-specific fee schedules need further integration before using this as an accounting-grade price recommendation.
- `keyword_rankings` table is provided for rank history; this first UI version captures the current rank on the keyword record and does not yet provide a bulk rank import screen.
