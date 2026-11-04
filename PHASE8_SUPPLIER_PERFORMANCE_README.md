PHASE 8 — SUPPLIER PERFORMANCE, PURCHASE ANALYTICS & COST TRACKING

This patch adds supplier performance analytics on top of the existing supplier, purchase and Phase 7 reorder modules.

FEATURES
- Supplier scorecards: purchase count/value, units ordered/received, delivery performance, overdue ordered POs and internal review average.
- Expected delivery date on purchase orders. On-time rate is evaluated only for RECEIVED POs that have an expected delivery date.
- Cost comparison: compares the two latest purchase-line unit costs for each product in the selected date range.
- Supplier detail page with purchase history and internal review history.
- Internal 1–5 ratings for delivery reliability, product quality and pricing competitiveness.
- CSV export of supplier performance.

INSTALL
1. Back up the project and database.
2. Extract/merge the ZIP into the Laravel project root, preserving folders. Keep your .env file.
3. Run:
   php artisan migrate
   php artisan optimize:clear
4. Verify routes:
   php artisan route:list --name=supplier-analytics
5. Open:
   /supplier-analytics

IMPORTANT NOTES
- Expected delivery date is optional. Existing historical POs remain valid; they are excluded from on-time rate until an expected date is recorded.
- On-time means the PO was fully received on or before the expected delivery date. Partial receipts do not count as completed deliveries.
- Overdue means status ORDERED and expected delivery date is before today.
- Cost comparison uses unit_cost excluding GST and compares the two most recent purchase lines within the selected period; it is a purchase-history comparison, not a supplier quote feed.
- Ratings are internal subjective reviews and are not generated automatically.
- No external supplier API, emails, purchase placement, payments or marketplace API calls occur.
- No existing Phase 8 reports/automation are replaced. This adds a separate Supplier Analytics section.

VALIDATION
PHP syntax lint and ZIP integrity should be checked after extraction. Database/browser runtime testing must be performed locally using the project's configured MySQL database. Do not run migrate:fresh.
