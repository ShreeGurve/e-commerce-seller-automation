<?php
namespace Tests\Feature;

use App\Models\{Inventory, Marketplace, Order, Product, ProductCostHistory, User};
use App\Services\{InventoryService, OrderService, RefundService, ReturnService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the database. Only run on in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
    }

    private function user(): User { return User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']); }
    private function product(int $stock = 10): Product
    {
        $p = Product::create(['sku' => 'SKU1', 'name' => 'P1', 'gst_rate' => 18]);
        ProductCostHistory::create(['product_id' => $p->id, 'cost_type' => 'COGS', 'amount' => '120', 'effective_from' => '2026-01-01', 'effective_to' => '2026-09-30']);
        ProductCostHistory::create(['product_id' => $p->id, 'cost_type' => 'COGS', 'amount' => '135', 'effective_from' => '2026-10-01']);
        if ($stock) app(InventoryService::class)->record($p, 'STOCK_IN', $stock);
        return $p;
    }
    private function order(Product $p, int $qty = 2, string $ext = 'AMZ-1', string $date = '2026-10-05 10:00'): Order
    {
        $mp = Marketplace::firstOrCreate(['code' => 'amazon'], ['name' => 'Amazon']);
        return app(OrderService::class)->create(
            ['marketplace_id' => $mp->id, 'external_order_id' => $ext, 'order_date' => $date],
            [['product_id' => $p->id, 'quantity' => $qty, 'unit_price' => '118']], $this->user());
    }
    private function avail(Product $p): int { return (int) Inventory::where('product_id', $p->id)->value('available_qty'); }

    public function test_order_deducts_stock_and_snapshots_cost_on_order_date(): void
    {
        $p = $this->product();
        $old = $this->order($p, 2, 'A1', '2026-09-15 10:00');
        $new = $this->order($p, 1, 'A2', '2026-10-05 10:00');
        $this->assertSame('120.00', (string) $old->items->first()->cogs_at_sale);
        $this->assertSame('135.00', (string) $new->items->first()->cogs_at_sale);
        $this->assertSame('236.00', (string) $old->total_amount);
        $this->assertSame('36.00', (string) $old->tax_amount);   // 236 x 18/118
        $this->assertSame(7, $this->avail($p));
        $this->assertCount(0, app(InventoryService::class)->mismatches());
    }

    public function test_duplicate_marketplace_order_is_rejected(): void
    {
        $p = $this->product();
        $this->order($p, 1, 'DUP');
        try { $this->order($p, 1, 'DUP'); $this->fail('expected duplicate rejection'); } catch (ValidationException) {}
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $this->avail($p));
    }

    public function test_cancel_restocks_and_bad_transition_is_blocked(): void
    {
        $p = $this->product();
        $o = $this->order($p, 3);
        $this->assertSame(7, $this->avail($p));
        try { app(OrderService::class)->changeStatus($o, 'DELIVERED'); $this->fail('invalid transition'); } catch (ValidationException) {}
        app(OrderService::class)->changeStatus($o, 'CANCELLED');
        $this->assertSame(10, $this->avail($p));
    }

    private function shipped(Order $o): void
    {
        foreach (['PACKED', 'SHIPPED'] as $s) app(OrderService::class)->changeStatus($o, $s);
    }

    public function test_return_inspection_puts_stock_in_right_bucket_and_blocks_over_return(): void
    {
        $p = $this->product(); $o = $this->order($p, 3); $this->shipped($o);
        $item = $o->items->first(); $rs = app(ReturnService::class); $u = $this->user();
        $d = ['return_type' => 'CHANGED_MIND', 'return_date' => '2026-10-08'];

        $r1 = $rs->create($o, $d, [$item->id => 1], $u);
        $this->assertSame('RETURNED', $o->fresh()->status);
        $rs->inspect($r1, [$r1->items->first()->id => ['received_qty' => 1, 'condition' => 'SELLABLE']], false, null, $u);
        $this->assertSame(8, $this->avail($p));

        $r2 = $rs->create($o, $d, [$item->id => 1], $u);
        $rs->inspect($r2, [$r2->items->first()->id => ['received_qty' => 1, 'condition' => 'DAMAGED']], true, null, $u);
        $inv = Inventory::where('product_id', $p->id)->first();
        $this->assertSame([8, 1], [$inv->available_qty, $inv->damaged_qty]);

        try { $rs->inspect($r2, [], false, null, $u); $this->fail('second inspection'); } catch (ValidationException) {}
        try { $rs->create($o, $d, [$item->id => 2], $u); $this->fail('over-return'); } catch (ValidationException) {}   // only 1 left
        $this->assertCount(0, app(InventoryService::class)->mismatches());
    }

    public function test_refund_cannot_exceed_order_total_and_full_refund_closes_the_loop(): void
    {
        $p = $this->product(); $o = $this->order($p, 2); $this->shipped($o); $u = $this->user();
        app(ReturnService::class)->create($o, ['return_type' => 'UNKNOWN', 'return_date' => '2026-10-08'], [$o->items->first()->id => 2], $u);
        $svc = app(RefundService::class); $d = ['refund_date' => '2026-10-08'];

        $svc->record($o, $d + ['amount' => '100'], $u);
        try { $svc->record($o, $d + ['amount' => '200'], $u); $this->fail('over-refund'); } catch (ValidationException) {}
        $svc->record($o, $d + ['amount' => '136'], $u);
        $this->assertSame('REFUNDED', $o->fresh()->status);
    }
}
