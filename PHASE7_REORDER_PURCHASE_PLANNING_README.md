PHASE 7 — SUPPLIER REORDERING & PURCHASE PLANNING

This patch builds on the supplier, purchase-order and inventory modules already present in the uploaded project. It adds:
- Reorder Planning screen at /reorders.
- Suggestions for active products at/below reorder_level (fallback: minimum_stock).
- Suggested target stock is 2x the configured threshold.
- Outstanding quantities on ORDERED POs are deducted; DRAFT POs are intentionally excluded.
- Create a DRAFT purchase order for a selected supplier and selected products. No email, supplier API call, or external order placement occurs.
- Partial purchase receiving: enter the delivered quantity for each line; only that quantity is posted through InventoryService. Remaining units stay outstanding. The PO becomes RECEIVED only when every line is fully received.
- Full receiving remains available through the existing receive endpoint/service call and now receives only outstanding quantities.

INSTALL
1. Back up your project and database.
2. Extract this ZIP into the Laravel project root, preserving folders, and replace the included files.
3. Run:
   php artisan optimize:clear
   php artisan route:list --name=reorders
   php artisan route:list --name=purchases.receive-quantities
4. Open /reorders. Ensure products have inventory thresholds configured and active suppliers exist.
5. Select a supplier, review/edit quantities and unit costs, then create a DRAFT PO. Review it under Purchases and manually mark it ORDERED by editing/saving the PO status when ready.
6. On a PO detail page, use Receive quantities for partial deliveries.

NO NEW MIGRATION REQUIRED. The uploaded project already contains suppliers, purchases, purchase_items, inventory and the Phase 6 inventory_stock_counts migration.

IMPORTANT
- Do not run migrate:fresh.
- Suggested unit cost is prefilled from current COGS; verify it against the supplier quote before ordering. GST is set to 0 on generated draft lines and can be edited on the PO before it is ordered.
- Only ORDERED purchase orders count as incoming stock in reorder calculations.
- No marketplace API calls, supplier communications, payment transactions or real supplier orders are triggered.

VALIDATION
PHP syntax lint and ZIP integrity were checked for the patch. Runtime database/browser testing was not possible in this environment; verify on your local copy after installation.
