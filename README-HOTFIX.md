# Package 12 Hotfix — Undefined `$source`

## Fix
The `report()` method validates `$source` before opening its database transaction, but the closure did not capture that variable. The closure `use` list now includes `$source`.

## Install
1. Back up `app/Services/MarketplaceListingComplianceService.php`.
2. Extract this ZIP at your Laravel project root, allowing it to overwrite the service file.
3. Run:

```powershell
php artisan optimize:clear
php artisan test --filter=MarketplaceListingComplianceServiceTest
php artisan test
```

The change is limited to closure variable capture; no migration or route changes are required.
