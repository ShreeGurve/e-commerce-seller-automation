# Seller Automation — Package 6: Shipment Tracking & Delivery Lifecycle

## Prerequisites

Install the existing Seller Automation baseline and Packages 1–5 first. This package extends the existing `order_fulfillments` and `order_fulfillment_events` tables and reuses the existing `OrderService` status-transition safeguards.

## Included

- Manual carrier tracking checkpoints for self-fulfilled orders: picked up, in transit, out for delivery, delivery attempted, delivered, exception, and return to sender.
- Tracking status, last tracking timestamp, and delivery-exception snapshot on the fulfillment record.
- Append-only shipment activity events with optional carrier note, location, event time, and acting user.
- Delivery confirmation moves a shipped order to `DELIVERED` through the existing `OrderService`, preserving order status history and profit refresh behavior.
- Shipment activity timeline and tracking-event form in the order fulfillment panel.
- Validation blocks updates for marketplace-fulfilled orders and orders that have not reached `SHIPPED`.
- No carrier API, marketplace API, label purchase, pickup booking, or shipping write request is made. Updates are manually entered only.

## Installation

1. Back up the application and database.
2. Extract this ZIP into the Laravel project root, preserving the `app`, `database`, `resources`, and `tests` paths.
3. Add this route inside the existing authenticated route group:

```php
use App\Http\Controllers\ShipmentTrackingController;

Route::post('/orders/{order}/fulfilment/tracking-events', [ShipmentTrackingController::class, 'store'])
    ->middleware('can:orders.edit')
    ->name('fulfilment.tracking-events.store');
```

4. Inspect and apply the migration:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

5. Run focused and full tests:

```powershell
php artisan test --filter=ShipmentTrackingServiceTest
php artisan test
```

## Workflow

1. Open a self-fulfilled order and complete its packing details.
2. Pack and dispatch it through the existing fulfillment workflow.
3. Once its status is `SHIPPED`, open the order details page and use **Shipment tracking update** to record carrier checkpoints.
4. Record `DELIVERED` only after delivery evidence is confirmed. This uses the existing order status service to move the order to `DELIVERED`.
5. Review all checkpoints in the **Shipment activity** timeline.

## Safety and behavior

- This package is a manual tracking-event workflow, not a live carrier integration.
- Only `SELF` fulfillment orders can receive manual carrier tracking events. Marketplace-fulfilled orders remain managed by their marketplace process.
- Tracking updates require the order to be `SHIPPED`.
- A delivery exception or attempted delivery is recorded as a tracking event and does not change the order's main status.
- `DELIVERED` updates the main order status using the existing transition service. It does not modify inventory quantities.
- Events are retained as an activity history; the fulfillment row stores the latest submitted tracking snapshot.
- Do not record customer names, phone numbers, email addresses, or full delivery addresses in the optional description/location fields.

## Files

- `app/Services/ShipmentTrackingService.php`
- `app/Http/Controllers/ShipmentTrackingController.php`
- `app/Models/OrderFulfillment.php`
- `database/migrations/2026_11_19_000003_add_shipment_tracking_lifecycle.php`
- `resources/views/orders/_fulfilment.blade.php`
- `tests/Feature/ShipmentTrackingServiceTest.php`
