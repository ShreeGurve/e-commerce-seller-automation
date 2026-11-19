# Package 5 hotfix — Amazon order mapping closure

## Problem fixed

`AmazonReadProvider` used a `static` arrow function while calling `$this->orderItemUnitPrice()` and `$this->orderItemCurrency()` inside that callback. PHP does not bind `$this` to a static closure, causing `Using $this when not in object context` when Amazon order lines were mapped.

## Fix

The `array_map` callback is now a non-static arrow function. It can safely call the provider's private helper methods. No schema, API request, or order-import behavior is otherwise changed.

## Install

1. Back up the existing file.
2. Extract this ZIP into the Laravel project root, replacing:
   `app/Marketplaces/Amazon/AmazonReadProvider.php`
3. Run:

```powershell
php artisan optimize:clear
php artisan test --filter=MarketplaceReadProviderTest
php artisan test
```

Expected focused result: all 5 provider tests pass. Then verify the full suite.
