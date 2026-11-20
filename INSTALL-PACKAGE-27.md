# Install Package 27

Copy the package files into the matching paths in your Laravel project.

## 1. Register routes in `routes/web.php`

Add the import once with the other controller imports:

```php
use App\Http\Controllers\MarketplaceWriteEvidenceController;
```

Add this group inside your existing authenticated route section (or keep the middleware below):

```php
Route::middleware(['auth', 'can:listings.manage'])
    ->prefix('marketplace-writes/evidence')
    ->name('marketplaces.writes.evidence.')
    ->group(function () {
        Route::get('/', [MarketplaceWriteEvidenceController::class, 'index'])
            ->name('index');

        Route::post('/', [MarketplaceWriteEvidenceController::class, 'store'])
            ->name('store');

        Route::get('/{evidence}', [MarketplaceWriteEvidenceController::class, 'show'])
            ->whereNumber('evidence')
            ->name('show');
    });
```

## 2. Migrate

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

## 3. Verify exact routes

```powershell
php artisan route:list --path=marketplace-writes/evidence
```

Expected:

| Method | URI | Route name |
|---|---|---|
| GET | marketplace-writes/evidence | marketplaces.writes.evidence.index |
| POST | marketplace-writes/evidence | marketplaces.writes.evidence.store |
| GET | marketplace-writes/evidence/{evidence} | marketplaces.writes.evidence.show |

## 4. Open

`http://127.0.0.1/laravel/seller-automation/marketplace-writes/evidence`

## 5. Test checklist
- Confirm the three routes appear.
- Open the page with an account that has `listings.manage`.
- Create a general evidence record.
- Confirm an HTTP URL is rejected and an HTTPS URL is accepted.
- Confirm a different business cannot see the record.
- Confirm filters and pagination work.

Do not run `migrate:fresh` against your existing database.
