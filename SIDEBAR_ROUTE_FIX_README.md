# Sidebar active-route correction

## Issue
The `Inventory` sidebar item used the route pattern `inventory*`. Laravel's route-name wildcard matching also matched `inventory-forecast.index`, so both Inventory and Inventory Forecast appeared active on `/inventory-forecast`.

## Fix
- Changed sidebar route-name patterns from broad prefixes such as `inventory*` to namespace-aware patterns such as `inventory.*`.
- Applied the same delimiter-safe pattern to all route-name wildcard sidebar entries.
- Kept explicit URL-path patterns (those containing `/`) as path matches.
- Made the active-state callback's conditional precedence explicit.

## Install
1. Back up `resources/views/layouts/app.blade.php`.
2. Extract this ZIP into the Laravel project root, replacing that file.
3. Run:

```bash
php artisan view:clear
php artisan optimize:clear
```

4. Verify:
   - `/inventory-forecast` highlights only **Inventory Forecast**.
   - `/inventory` highlights only **Inventory**.
   - Check Products, Purchases, Orders, Returns, Reports and other sidebar pages for one active item only.

## Validation
- PHP syntax check passed for the updated Blade/PHP file.
- Static check of 31 sidebar patterns against route names found no remaining prefix collisions.
- Browser verification was not run in this environment.
