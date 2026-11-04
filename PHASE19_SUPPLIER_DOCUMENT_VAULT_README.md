# Phase 19 – Supplier Document Vault & Compliance Management

Additive patch for the Seller Automation Laravel application. No full project replacement is required.

## Included files
- `app/Models/SupplierDocument.php`
- `app/Http/Controllers/SupplierDocumentController.php`
- `database/migrations/2026_11_04_000019_create_supplier_documents_table.php`
- `resources/views/suppliers/documents.blade.php`

## 1. Copy files
Extract this ZIP into the Laravel project root and allow the four files to merge into their matching folders. Do not replace existing supplier or relationship files.

## 2. Add routes
Inside the existing authenticated route group in `routes/web.php`, add:

```php
Route::get('/supplier-documents', [\App\Http\Controllers\SupplierDocumentController::class, 'index'])
    ->middleware('can:purchases.view')->name('supplier-documents.index');
Route::post('/supplier-documents', [\App\Http\Controllers\SupplierDocumentController::class, 'store'])
    ->middleware('can:purchases.create')->name('supplier-documents.store');
Route::get('/supplier-documents/{document}/download', [\App\Http\Controllers\SupplierDocumentController::class, 'download'])
    ->middleware('can:purchases.view')->name('supplier-documents.download');
Route::patch('/supplier-documents/{document}/review', [\App\Http\Controllers\SupplierDocumentController::class, 'review'])
    ->middleware('can:purchases.edit')->name('supplier-documents.review');
```

## 3. Run migration and clear cache

```powershell
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=supplier-documents
```

Expected routes: index (GET), store (POST), download (GET), review (PATCH).

## 4. Open module

`http://127.0.0.1/laravel/seller-automation/supplier-documents`

## Security and behaviour
- Files are stored on Laravel's private `local` disk, never in `public` storage. No `storage:link` is required.
- Downloads pass through an authenticated, permission-protected controller; files are not exposed by public URLs.
- Upload accepts PDF/JPG/JPEG/PNG/WEBP up to 10 MB.
- New documents enter `PENDING`; users with `purchases.edit` can approve, reject or return to pending with notes.
- Expiry indicators show expired and expiring-within-30-days records. This phase displays compliance status in the module; it does not send email or scheduled notifications.
- Deleting or changing a review status does not automatically delete stored files. Supplier deletion cascades the document database records; consider archiving suppliers rather than deleting them when retaining compliance evidence is required.
- No external supplier communications, marketplace calls, payments, purchase orders or inventory changes are performed.

## Verification
PHP syntax and ZIP integrity checks are performed when packaging. Run the migration and verify the routes in your local environment. Database/browser testing is not claimed.
