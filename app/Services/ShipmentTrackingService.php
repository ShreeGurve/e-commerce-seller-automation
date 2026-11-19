<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\OrderFulfillmentEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentTrackingService
{
    public const STATUSES = [
        'PICKED_UP' => 'Picked up by carrier',
        'IN_TRANSIT' => 'In transit',
        'OUT_FOR_DELIVERY' => 'Out for delivery',
        'DELIVERY_ATTEMPTED' => 'Delivery attempted',
        'DELIVERED' => 'Delivered',
        'EXCEPTION' => 'Delivery exception',
        'RETURN_TO_SENDER' => 'Return to sender',
    ];

    public function __construct(private OrderService $orders) {}

    public function record(
        Order $order,
        string $status,
        User $user,
        ?string $description = null,
        ?string $location = null,
        ?string $occurredAt = null,
    ): OrderFulfillmentEvent {
        if (! array_key_exists($status, self::STATUSES)) {
            throw ValidationException::withMessages(['tracking_status' => 'Select a supported shipment tracking event.']);
        }

        return DB::transaction(function () use ($order, $status, $user, $description, $location, $occurredAt) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->fulfillment_type !== 'SELF') {
                throw ValidationException::withMessages(['tracking_status' => 'Manual carrier tracking is only available for self-fulfilled orders.']);
            }
            if ($locked->status !== 'SHIPPED') {
                throw ValidationException::withMessages(['tracking_status' => 'Tracking events can only be recorded for shipped orders.']);
            }

            $eventAt = $occurredAt ? Carbon::parse($occurredAt) : now();
            $fulfillment = $locked->fulfillment()->firstOrNew();
            $fulfillment->order_id = $locked->id;
            $fulfillment->tracking_status = $status;
            $fulfillment->last_tracking_at = $eventAt;
            $fulfillment->delivery_exception = $status === 'EXCEPTION'
                ? ($description ?: 'Carrier reported a delivery exception.')
                : ($status === 'DELIVERY_ATTEMPTED' ? ($description ?: 'Delivery attempt was unsuccessful.') : null);
            $fulfillment->save();

            $event = OrderFulfillmentEvent::create([
                'order_id' => $locked->id,
                'event_type' => $status,
                'description' => $description ?: self::STATUSES[$status],
                'meta' => array_filter([
                    'source' => 'MANUAL_CARRIER_UPDATE',
                    'location' => $location,
                    'occurred_at' => $eventAt->toISOString(),
                ], fn ($value) => $value !== null && $value !== ''),
                'user_id' => $user->id,
                'created_at' => $eventAt,
            ]);

            if ($status === 'DELIVERED') {
                $this->orders->changeStatus($locked, 'DELIVERED', $user, 'Delivery confirmed from shipment tracking update.');
            }

            return $event;
        });
    }
}
