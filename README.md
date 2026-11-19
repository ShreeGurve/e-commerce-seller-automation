# Seller Automation — Package 9: Dynamic Pricing Rules & Recommendation Engine

## Prerequisites

Install the validated Seller Automation baseline and Packages 1–8. This package builds on the existing `PricingService`, fee rules, product cost history, competitor-price records, `PricingRecommendation` approval workflow, and internal price history.

## What this package adds

- One dynamic pricing rule per product + marketplace.
- Strategies: target-margin pricing, match lowest recorded competitor, undercut lowest recorded competitor, or price above the lowest recorded competitor.
- Optional seller-defined floor and ceiling prices.
- Minimum margin protection, with the existing cost/fee-based minimum price always enforced.
- A run history showing rules checked, recommendations created, and skipped rules.
- A manual **Run rules now** action and an Artisan command for scheduling.
- Generated recommendations enter the existing `pending` approval queue. Existing approval and internal price-history functions remain the only path to record a new price.

## Safety and data-source limits

- No automatic Amazon or Flipkart price publication is implemented or enabled.
- This package does not scrape competitor pages or claim access to live competitor prices. Competitor strategies use only competitor prices already entered/imported into the existing `competitor_prices` table from sources the seller is authorized to use.
- If a competitor price is unavailable, a competitor strategy is skipped; it does not silently fall back to an unrelated market estimate.
- A recommendation cannot be created below the existing fee/cost-derived minimum or the rule's floor. If the safe price exceeds the ceiling, the rule is skipped.
- Pending or approved recommendations prevent duplicate active proposals for the same product/channel.
- No stock, order, listing, or marketplace price is changed by this package.

## Installation

1. Back up the project and database.
2. Extract the ZIP into the Laravel project root (archive paths begin with `app/`, `database/`, `resources/`, and `tests/`).
3. Add the routes below inside the existing authenticated route group in `routes/web.php`. If your project already has any of these named routes, keep the existing definition and do not duplicate it.

```php
use App\Http\Controllers\DynamicPricingRuleController;
use App\Http\Controllers\PricingRecommendationController;

// Dynamic pricing rules
Route::get('/pricing/dynamic-rules', [DynamicPricingRuleController::class, 'index'])
    ->middleware('can:products.view')->name('pricing-dynamic.index');
Route::middleware('can:products.edit')->group(function () {
    Route::post('/pricing/dynamic-rules', [DynamicPricingRuleController::class, 'store'])->name('pricing-dynamic.store');
    Route::put('/pricing/dynamic-rules/{dynamicPricingRule}', [DynamicPricingRuleController::class, 'update'])->name('pricing-dynamic.update');
    Route::delete('/pricing/dynamic-rules/{dynamicPricingRule}', [DynamicPricingRuleController::class, 'destroy'])->name('pricing-dynamic.destroy');
    Route::post('/pricing/dynamic-rules/run', [DynamicPricingRuleController::class, 'run'])->name('pricing-dynamic.run');
});

// Existing recommendation approval workflow (add only if these routes are not already present)
Route::get('/pricing-recommendations', [PricingRecommendationController::class, 'index'])
    ->middleware('can:products.view')->name('pricing-recommendations.index');
Route::middleware('can:products.edit')->group(function () {
    Route::post('/pricing/{product}/{marketplace}/recommendations', [PricingRecommendationController::class, 'store'])->name('pricing-recommendations.store');
    Route::post('/pricing-recommendations/{pricingRecommendation}/apply', [PricingRecommendationController::class, 'apply'])->name('pricing-recommendations.apply');
});
Route::post('/pricing-recommendations/{pricingRecommendation}/approve', [PricingRecommendationController::class, 'approve'])
    ->middleware('can:products.view')->name('pricing-recommendations.approve');
Route::post('/pricing-recommendations/{pricingRecommendation}/reject', [PricingRecommendationController::class, 'reject'])
    ->middleware('can:products.view')->name('pricing-recommendations.reject');
```

> The controller itself checks reviewer permissions (`Super Admin`, `settings.manage`, or `users.manage`). The route middleware only establishes authenticated product-view access; do not remove the controller-level reviewer check.

4. Add the schedule to `routes/console.php` without replacing existing entries:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('marketplaces:dynamic-pricing')->dailyAt('02:00')->withoutOverlapping();
```

5. Inspect and run the migration:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

6. Run tests:

```powershell
php artisan test --filter=DynamicPricingServiceTest
php artisan test
```

7. Open **Pricing → Dynamic Pricing Rules** at `/pricing/dynamic-rules`. Create a rule for each product/channel. Use **Run rules now** to generate proposals. The daily schedule runs the same rule engine automatically.

## Artisan commands

```powershell
php artisan marketplaces:dynamic-pricing
php artisan marketplaces:dynamic-pricing --business=1
php artisan marketplaces:dynamic-pricing --marketplace=1
```

Keep the Laravel scheduler running in deployment (`php artisan schedule:run` every minute via Windows Task Scheduler) for the daily run.

## Files

- `app/Models/DynamicPricingRule.php`
- `app/Models/DynamicPricingRun.php`
- `app/Services/DynamicPricingService.php`
- `app/Console/Commands/RunDynamicPricing.php`
- `app/Http/Controllers/DynamicPricingRuleController.php`
- `database/migrations/2026_11_19_000006_create_dynamic_pricing_rule_tables.php`
- `resources/views/pricing/dynamic-rules.blade.php`
- `tests/Feature/DynamicPricingServiceTest.php`

## Important note about approval

A rule run creates a `pending` `PricingRecommendation`; it does not approve it. A reviewer must approve it, and a permitted user can then record the approved amount in internal `price_history`. The user must still update the marketplace listing manually. This separation is intentional until the marketplace write APIs, permissions, and account-specific constraints are validated.
