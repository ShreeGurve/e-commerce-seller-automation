<?php
namespace Tests\Feature;

use App\Models\{Category, FeeRule, Marketplace, Order, OrderItemFee, OrderProfit, Product, ProductCostHistory, User};
use App\Services\{InventoryService, OrderService, ProfitService, RefundService, ReturnService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitEngineTest extends TestCase
{
    use RefreshDatabase;

    private ?User $u = null; private Product $p; private Marketplace $mp;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the database. Only run on in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
        $this->u = User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']);
        $this->mp = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $this->p = Product::create(['sku' => 'SKU1', 'name' => 'P1', 'gst_rate' => 18]);
        ProductCostHistory::create(['product_id' => $this->p->id, 'cost_type' => 'COGS', 'amount' => '50', 'effective_from' => '2026-01-01']);
        app(InventoryService::class)->record($this->p, 'STOCK_IN', 10);
        $this->rule('REFERRAL', 10, 0);   // 10% of price
        $this->rule('CLOSING', 0, 10);    // Rs 10 per unit
    }

    private function rule(string $type, float $pct, float $fixed, ?int $cat = null): FeeRule
    {
        return FeeRule::create(['marketplace_id' => $this->mp->id, 'category_id' => $cat, 'fee_type' => $type, 'percentage' => $pct, 'fixed_amount' => $fixed, 'effective_from' => '2026-01-01']);
    }
    /** 2 units x Rs 118 (GST 18% inside) = 236.00 */
    private function order(string $ext = 'A1'): Order
    {
        return app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => $ext, 'order_date' => now()->subDays(3)],
            [['product_id' => $this->p->id, 'quantity' => 2, 'unit_price' => '118']], $this->u);
    }
    private function profit(): string { return (string) OrderProfit::first()->estimated_profit; }

    public function test_estimated_profit_uses_fee_rules_cost_snapshot_and_fee_gst(): void
    {
        $this->order();
        // revenue 200.00 - cogs 100.00 - fees (23.60 + 20.00 + GST 4.25 + 3.60 = 51.45) = 48.55
        $this->assertSame('48.55', $this->profit());
        $this->assertSame(2, OrderItemFee::where('source', 'ESTIMATED')->count());
    }

    public function test_category_rule_beats_generic_rule(): void
    {
        $c = Category::forceCreate(['name' => 'Kitchen', 'slug' => 'kitchen']);
        $this->p->update(['category_id' => $c->id]);
        $this->rule('REFERRAL', 5, 0, $c->id);
        $this->order();
        $this->assertSame('62.48', $this->profit());     // 5% referral, not 10%
    }

    public function test_changing_a_rule_then_recalculating_updates_profit(): void
    {
        $this->order();
        FeeRule::where('fee_type', 'REFERRAL')->update(['percentage' => 20]);
        $this->assertSame('48.55', $this->profit());                      // old orders do not change by themselves
        $this->assertSame(1, app(ProfitService::class)->recalcAll());
        $this->assertSame('20.70', $this->profit());
    }

    public function test_sellable_return_gives_cost_back_and_refund_reduces_revenue(): void
    {
        $o = $this->order();
        foreach (['PACKED', 'SHIPPED'] as $s) app(OrderService::class)->changeStatus($o, $s);
        $ret = app(ReturnService::class)->create($o, ['return_type' => 'UNKNOWN', 'return_date' => now()->toDateString()], [$o->items->first()->id => 1], $this->u);
        app(ReturnService::class)->inspect($ret, [$ret->items->first()->id => ['received_qty' => 1, 'condition' => 'SELLABLE']], false, null, $this->u);
        app(RefundService::class)->record($o, ['amount' => '118', 'refund_date' => now()->toDateString()], $this->u);
        // revenue 200 - cogs 50 (1 unit restocked) - fees 51.45 - refund 100.00 = -1.45
        $this->assertSame('-1.45', $this->profit());
    }

    public function test_cancelled_orders_have_no_profit_rows(): void
    {
        $o = $this->order();
        $this->assertSame(1, OrderProfit::count());
        app(OrderService::class)->changeStatus($o, 'CANCELLED');
        $this->assertSame([0, 0], [OrderProfit::count(), OrderItemFee::count()]);
    }
}
