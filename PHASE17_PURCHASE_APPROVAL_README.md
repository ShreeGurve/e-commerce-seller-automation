# Phase 17 — Smart Reorder Approval Workflow

## What this adds
- Reuses Phase 7 reorder suggestions and the existing `approval_requests` workflow.
- Changes reorder submission from immediate draft-PO creation to a purchase approval request.
- Captures supplier, requested date, selected products, quantities, unit costs and estimated subtotal in the approval's proposed-value snapshot.
- Revalidates that selected products are active and still at/below their reorder threshold at submission time.
- Adds a Reorder Approvals page. Once an existing approver approves the request, an authorised user can convert it into a DRAFT purchase order.
- Uses a database transaction and row lock to prevent the same approval being converted twice.

## Files
- `app/Http/Controllers/ReorderApprovalController.php` (new)
- `resources/views/reorders/index.blade.php` (updated: submits for approval)
- `resources/views/reorders/approvals.blade.php` (new)

## Installation
1. Back up `resources/views/reorders/index.blade.php` if it exists.
2. Extract the ZIP into the Laravel project root.
3. In `routes/web.php`, inside the existing authenticated route group, add these routes. Keep the existing Phase 7 `GET /reorders` route; replace the old `POST /reorders/purchase` route with the new approval route.

```php
use App\Http\Controllers\ReorderApprovalController;

Route::post('/reorders/approval', [ReorderApprovalController::class, 'store'])
    ->middleware('can:purchases.create')->name('reorders.approval');
Route::get('/reorders/approvals', [ReorderApprovalController::class, 'index'])
    ->middleware('can:approvals.view')->name('reorders.approvals');
Route::post('/reorders/approvals/{approvalRequest}/draft', [ReorderApprovalController::class, 'createDraft'])
    ->middleware('can:purchases.create')->name('reorders.approvals.draft');
```

4. Ensure the `ApprovalController` routes are present and approvers have the existing approval-review permissions. The existing approval page handles approve/reject; this patch does not replace it.
5. Run:

```bash
php artisan optimize:clear
php artisan route:list --name=reorders
php artisan route:list --name=approvals
```

6. Open `/reorders`, select products and submit the plan. Review it under `/approvals`. After approval, open `/reorders/approvals` and click **Create draft PO**.

## Important behaviour
- No new migration is required; it reuses the existing `approval_requests` table.
- The estimate is quantity × unit cost and excludes GST, shipping and other charges. The generated draft PO is recalculated by the existing `PurchaseService`.
- An approved plan creates only a DRAFT PO. It does not mark the PO ordered, send supplier email, submit an external PO, make a payment, or change stock.
- The existing Phase 7 direct draft-PO route may remain in routes for backward compatibility, but the updated reorder screen no longer uses it. Remove or permission-restrict that route if your policy requires all reorder-created POs to pass approval.
- This is an internal purchase workflow; no marketplace API calls are made.

## Validation
PHP syntax and ZIP integrity are checked during packaging. Local database/browser execution is not available here; verify the routes and workflow in your local environment.
