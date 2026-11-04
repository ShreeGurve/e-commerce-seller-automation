# Seller Automation — Phase 9: Inventory Forecasting & Demand Planning

## Included
- Internal demand forecasting from historical order items.
- Test orders (`orders.source = TEST`) are excluded.
- Only CONFIRMED, PACKED, SHIPPED, DELIVERED and CLOSED orders count as demand. Cancelled, returned and refunded orders are excluded.
- Product-level average daily demand and selectable 7/14/30/60-day demand horizon.
- Estimated stock-out dates using available stock less reserved stock.
- Suggested replenishment quantity based on forecast demand during lead time + safety stock days, less current sellable stock and outstanding quantities on ORDERED purchase orders.
- Forecast snapshots and retrospective accuracy comparison after each forecast period completes.
- Sidebar navigation entry: Inventory Forecast.

## Important calculation notes
- Lookback window can be 30, 60 or 90 calendar days, including today.
- Lead time and safety stock days are user-adjustable on the forecast screen; the application does not currently maintain a per-supplier lead-time field.
- Incoming quantities count only purchase items on purchases with status ORDERED. Draft and cancelled purchase orders are excluded.
- Stock position = max(available quantity − reserved quantity, 0) + outstanding ordered purchase quantities.
- Suggested reorder quantity = max(forecast daily demand × (lead time days + safety stock days) − stock position, 0), rounded up to whole units.
- The demand forecast is a simple historical average, not a machine-learning prediction. Promotions, seasonality, stockout-constrained sales and returns are not modelled.
- Accuracy is calculated as 100 − absolute error / actual units × 100. When actual demand is zero, accuracy is 0% if a positive quantity was forecast and 100% if zero was forecast.
- Forecast snapshots are saved per active product. Accuracy becomes available after the forecast end date has passed.

## Installation
1. Back up the application folder and database.
2. Extract/merge this ZIP over the Laravel project root. Preserve `.env` and do not delete existing files.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=inventory-forecast
```

4. Open `/inventory-forecast` under the existing application base URL.
5. Choose lookback, forecast horizon, lead time and safety stock days, then click Calculate.
6. Use Save forecast snapshot to preserve the prediction for later accuracy comparison.
7. Use Reorder Planning to create a draft PO after reviewing supplier, quantities and costs.

## Routes added
- `GET /inventory-forecast` — `inventory-forecast.index` (`purchases.view`)
- `POST /inventory-forecast/snapshot` — `inventory-forecast.snapshot` (`purchases.edit`)

## Safety
- No inventory quantities are modified by forecasting.
- No purchase orders are created automatically.
- No supplier is contacted and no payments are made.
- No marketplace APIs are called.
- Existing manual reorder and purchase workflows remain unchanged.

## Validation
PHP syntax checks and ZIP integrity checks were run for this patch. Runtime/database/browser testing requires the user's local `.env`, MySQL database and Composer dependencies.
