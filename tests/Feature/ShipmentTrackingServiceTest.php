<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Models\Order;
use App\Models\OrderFulfillmentEvent;
use App\Models\User;
use App\Services\ShipmentTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShipmentTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Use DB_CONNECTION=sqlite and DB_DATABASE=:memory: for isolated shipment tests.');
        }
        parent::setUp();
    }

    private function fixture(string $status = 'SHIPPED', string $fulfillmentType = 'SELF'): array
    {
        $user = User::forceCreate(['name' => 'Tracking Tester', 'email' => uniqid('tracking_', true).'@test.local', 'password' => 'x']);
        $marketplace = Marketplace::firstOrCreate(['code' => 'amazon'], ['name' => 'Amazon']);
        $order = Order::forceCreate([
            'marketplace_id' => $marketplace->id,
            'external_order_id' => uniqid('EXT-'),
            'order_number' => 'TRK-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)),
            'order_date' => now(),
            'status' => $status,
            'fulfillment_type' => $fulfillmentType,
            'subtotal' => 100,
            'shipping_charged' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'source' => 'TEST',
            'created_by' => $user->id,
        ]);
        return [$order, $user];
    }

    public function test_records_manual_tracking_event_and_updates_fulfillment_snapshot(): void
    {
        [$order, $user] = $this->fixture();
        $event = app(ShipmentTrackingService::class)->record(
            $order,
            'IN_TRANSIT',
            $user,
            'Departed sorting facility',
            'Ahmedabad hub',
            '2026-11-19 10:30:00',
        );

        $this->assertSame('IN_TRANSIT', $event->event_type);
        $this->assertSame('Ahmedabad hub', $event->meta['location']);
        $this->assertDatabaseHas('order_fulfillments', [
            'order_id' => $order->id,
            'tracking_status' => 'IN_TRANSIT',
            'delivery_exception' => null,
        ]);
        $this->assertDatabaseHas('order_fulfillment_events', [
            'order_id' => $order->id,
            'event_type' => 'IN_TRANSIT',
        ]);
    }

    public function test_tracking_updates_reject_unshipped_orders(): void
    {
        [$order, $user] = $this->fixture('PACKED');

        try {
            app(ShipmentTrackingService::class)->record($order, 'IN_TRANSIT', $user);
            $this->fail('Expected unshipped order tracking update to be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, OrderFulfillmentEvent::where('order_id', $order->id)->count());
        }
    }

    public function test_marketplace_fulfilled_orders_cannot_receive_manual_carrier_events(): void
    {
        [$order, $user] = $this->fixture('SHIPPED', 'MARKETPLACE');

        try {
            app(ShipmentTrackingService::class)->record($order, 'IN_TRANSIT', $user);
            $this->fail('Expected marketplace-fulfilled order to be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, OrderFulfillmentEvent::where('order_id', $order->id)->count());
        }
    }

    public function test_unknown_tracking_status_is_rejected(): void
    {
        [$order, $user] = $this->fixture();

        try {
            app(ShipmentTrackingService::class)->record($order, 'UNKNOWN', $user);
            $this->fail('Expected unsupported tracking status to be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, OrderFulfillmentEvent::where('order_id', $order->id)->count());
        }
    }

    public function test_delivery_event_uses_existing_order_status_transition(): void
    {
        [$order, $user] = $this->fixture();
        $this->mock(\App\Services\ProfitService::class, function ($mock) {
            $mock->shouldReceive('safe')->once();
        });

        app(ShipmentTrackingService::class)->record($order, 'DELIVERED', $user, 'Received by recipient');

        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => 'SHIPPED',
            'to_status' => 'DELIVERED',
        ]);
    }
}
