<?php

namespace Tests\Feature;

use App\Models\{Marketplace, Order, OrderItem, Product, ProductReturn, ReturnInspection, User};
use App\Services\MarketplaceReturnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceReturnsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::create(['name' => 'Return Reviewer', 'email' => uniqid().'@example.test', 'password' => 'password']);
        $marketplace = Marketplace::create(['code' => 'ret-'.uniqid(), 'name' => 'Return Test Market', 'is_active' => true]);
        $product = Product::create(['sku' => 'RET-'.uniqid(), 'name' => 'Return Test Product']);
        $order = Order::create(['marketplace_id' => $marketplace->id, 'external_order_id' => 'EXT-'.uniqid(), 'order_number' => 'ORD-'.strtoupper(uniqid()),
            'order_date' => now(), 'status' => 'DELIVERED', 'fulfillment_type' => 'SELF', 'subtotal' => 200, 'shipping_charged' => 0,
            'tax_amount' => 0, 'total_amount' => 200, 'source' => 'API']);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'external_line_id' => 'LINE-1', 'marketplace_sku' => 'SKU-RET-1',
            'quantity' => 2, 'qty_deducted' => 2, 'unit_price' => 100, 'discount' => 0, 'gst_rate' => 0, 'tax_amount' => 0,
            'line_total' => 200, 'cogs_at_sale' => 40, 'packaging_at_sale' => 0, 'other_cost_at_sale' => 0]);
        return compact('user', 'marketplace', 'product', 'order', 'item');
    }

    public function test_return_staging_is_idempotent_and_does_not_create_operational_return(): void
    {
        $f = $this->fixture(); $service = app(MarketplaceReturnsService::class);
        $payload = ['external_return_id' => 'RET-EXT-1', 'external_order_id' => $f['order']->external_order_id, 'return_date' => now()->toDateString(),
            'return_type' => 'DAMAGED', 'items' => [['external_line_id' => 'LINE-1', 'quantity' => 1]]];
        $first = $service->stageReturn($f['marketplace'], $payload);
        $second = $service->stageReturn($f['marketplace'], $payload);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('PENDING', $second->status);
        $this->assertSame(0, ProductReturn::count());
    }

    public function test_approved_return_import_creates_return_and_matching_line_once(): void
    {
        $f = $this->fixture(); $service = app(MarketplaceReturnsService::class);
        $staged = $service->stageReturn($f['marketplace'], ['external_return_id' => 'RET-EXT-2', 'external_order_id' => $f['order']->external_order_id,
            'return_date' => now()->toDateString(), 'return_type' => 'DAMAGED', 'items' => [['external_line_id' => 'LINE-1', 'quantity' => 1]]]);
        $return = $service->approveReturn($staged, $f['user']);
        $again = $service->approveReturn($staged, $f['user']);
        $this->assertSame($return->id, $again->id);
        $this->assertSame('IMPORTED', $staged->fresh()->status);
        $this->assertSame(1, $return->items->count());
        $this->assertSame('REQUESTED', $return->status);
    }

    public function test_import_rejects_return_quantity_above_remaining_order_quantity(): void
    {
        $f = $this->fixture(); $service = app(MarketplaceReturnsService::class);
        $first = $service->stageReturn($f['marketplace'], ['external_return_id' => 'RET-EXT-3', 'external_order_id' => $f['order']->external_order_id,
            'return_date' => now()->toDateString(), 'items' => [['external_line_id' => 'LINE-1', 'quantity' => 2]]]);
        $service->approveReturn($first, $f['user']);
        $second = $service->stageReturn($f['marketplace'], ['external_return_id' => 'RET-EXT-4', 'external_order_id' => $f['order']->external_order_id,
            'return_date' => now()->toDateString(), 'items' => [['external_line_id' => 'LINE-1', 'quantity' => 1]]]);
        try {
            $service->approveReturn($second, $f['user']);
            $this->fail('Over-return quantity must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0', $exception->errors());
        }
    }

    public function test_inspection_sets_disposition_and_claim_eligibility_without_stock_mutation(): void
    {
        $f = $this->fixture(); $service = app(MarketplaceReturnsService::class);
        $staged = $service->stageReturn($f['marketplace'], ['external_return_id' => 'RET-EXT-5', 'external_order_id' => $f['order']->external_order_id,
            'return_date' => now()->toDateString(), 'return_type' => 'DAMAGED', 'items' => [['external_line_id' => 'LINE-1', 'quantity' => 1]]]);
        $return = $service->approveReturn($staged, $f['user']);
        $item = $return->items->first();
        $service->inspect($return, [['return_item_id' => $item->id, 'received_qty' => 1, 'condition' => 'DAMAGED', 'restock_qty' => 0]], $f['user']);
        $this->assertSame('INSPECTED', $return->fresh()->status);
        $this->assertTrue($return->fresh()->claim_eligible);
        $this->assertSame(2, $f['item']->fresh()->qty_deducted);
        $this->assertSame('QUARANTINE', ReturnInspection::where('return_id', $return->id)->first()->disposition);
    }

    public function test_inspection_cannot_restock_damaged_units(): void
    {
        $f = $this->fixture(); $service = app(MarketplaceReturnsService::class);
        $staged = $service->stageReturn($f['marketplace'], ['external_return_id' => 'RET-EXT-6', 'external_order_id' => $f['order']->external_order_id,
            'return_date' => now()->toDateString(), 'items' => [['external_line_id' => 'LINE-1', 'quantity' => 1]]]);
        $return = $service->approveReturn($staged, $f['user']); $item = $return->items->first();
        try {
            $service->inspect($return, [['return_item_id' => $item->id, 'received_qty' => 1, 'condition' => 'DAMAGED', 'restock_qty' => 1]], $f['user']);
            $this->fail('Damaged stock cannot be marked for restock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.restock_qty', $exception->errors());
        }
    }

    public function test_refund_import_rejects_non_inr_and_is_staged_only(): void
    {
        $f = $this->fixture(); $service = app(MarketplaceReturnsService::class);
        $data = ['external_refund_id' => 'REF-EXT-1', 'external_order_id' => $f['order']->external_order_id, 'amount' => 50,
            'currency' => 'USD', 'refund_date' => now()->toDateString()];
        try {
            $service->stageRefund($f['marketplace'], $data);
            $this->fail('Non-INR refund should not enter the INR ledger.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency', $exception->errors());
        }
        $data['currency'] = 'INR';
        $staged = $service->stageRefund($f['marketplace'], $data);
        $this->assertSame('PENDING', $staged->status);
        $this->assertSame(0, \App\Models\Refund::count());
    }
}
