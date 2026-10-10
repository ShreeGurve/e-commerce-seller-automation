<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{Claim, ClaimDocument, Marketplace, Order, OrderProfit, Product, ProductCostHistory, ReconciliationIssue, ReturnMedia, Settlement, SettlementItem, User};
use App\Services\{ClaimService, InventoryService, OrderService, ProfitService, ReconciliationService, ReturnService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $u; private Product $p; private Marketplace $mp;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the database. Only run on in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
        $this->u = User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']);
        $this->mp = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon', 'adapter_class' => AmazonAdapter::class]);
        $this->p = Product::create(['sku' => 'SKU1', 'name' => 'P1', 'gst_rate' => 18]);
        ProductCostHistory::create(['product_id' => $this->p->id, 'cost_type' => 'COGS', 'amount' => '50', 'effective_from' => '2026-01-01']);
        app(InventoryService::class)->record($this->p, 'STOCK_IN', 50);   // no fee rules: order profit = 200 - 100 = 100.00
    }

    /** 2 units x Rs 118 = 236.00, already shipped */
    private function order(string $ext = 'A1'): Order
    {
        $o = app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => $ext, 'order_date' => now()->subDays(3)],
            [['product_id' => $this->p->id, 'quantity' => 2, 'unit_price' => '118']], $this->u);
        foreach (['PACKED', 'SHIPPED'] as $s) app(OrderService::class)->changeStatus($o, $s);
        return $o->fresh();
    }
    private function claim(Order $o, string $amount = '100', string $type = 'DAMAGED_ITEM'): Claim
    {
        return app(ClaimService::class)->create(['marketplace_id' => $this->mp->id, 'order_id' => $o->id, 'claim_type' => $type, 'claim_amount' => $amount], $this->u);
    }
    private function go(Claim $c, string $to, array $d = []): Claim { return app(ClaimService::class)->transition($c, $to, $d, $this->u); }
    private function item(array $x): SettlementItem
    {
        static $n = 0;
        $st = Settlement::firstOrCreate(['marketplace_id' => $this->mp->id, 'external_settlement_id' => 'S1']);
        return SettlementItem::create($x + ['settlement_id' => $st->id, 'posted_date' => now()->subDay()->toDateString(), 'row_hash' => sha1('h' . ++$n)]);
    }

    public function test_return_inspection_suggests_a_claim_and_duplicates_are_blocked(): void
    {
        $o = $this->order();
        $rs = app(ReturnService::class);
        $ret = $rs->create($o, ['return_type' => 'DAMAGED', 'return_date' => now()->toDateString()], [$o->items->first()->id => 2], $this->u);
        $rs->inspect($ret, [$ret->items->first()->id => ['received_qty' => 1, 'condition' => 'DAMAGED']], true, null, $this->u);

        $svc = app(ClaimService::class);
        $this->assertSame([$ret->id], $svc->candidates()->pluck('id')->all());
        $s = $svc->suggestFromReturn($ret->fresh());
        $this->assertSame(['SAFE_T', '236.00', $this->p->id], [$s['claim_type'], $s['claim_amount'], $s['product_id']]);   // Amazon -> SAFE-T; 2 units not sellable x 118

        $c = $svc->create($s, $this->u);
        $this->assertSame('DRAFT', $c->status);
        $this->assertStringStartsWith('CL-', $c->claim_number);
        $this->assertSame(now()->addDays(30)->toDateString(), $c->deadline->toDateString());   // filing window from Settings default
        $this->assertCount(0, $svc->candidates());
        $this->expectException(ValidationException::class);
        $svc->create($s, $this->u);                                                              // same order + type is already open
    }

    public function test_status_flow_decisions_and_appeal(): void
    {
        $c = $this->claim($this->order());
        try { $this->go($c, 'APPROVED'); $this->fail('draft cannot jump to approved'); } catch (ValidationException) {}

        $c = $this->go($c, 'SUBMITTED', ['external_claim_id' => 'CASE-9']);
        $this->assertSame('CASE-9', $c->external_claim_id);
        $c = $this->go($c, 'UNDER_REVIEW');
        try { $this->go($c, 'PARTIALLY_APPROVED', ['approved_amount' => '100']); $this->fail('partial must be below the claim'); } catch (ValidationException) {}
        $c = $this->go($c, 'PARTIALLY_APPROVED', ['approved_amount' => '60']);
        $this->assertSame(['60.00', '40.00'], [(string) $c->approved_amount, (string) $c->rejected_amount]);

        $c = $this->go($c, 'APPEAL', ['deadline' => now()->addDays(10)->toDateString()]);
        $this->assertSame(1, $c->appeal_count);
        $this->assertSame('60.00', (string) $c->approved_amount);                                // earlier approval stays until a new decision
        $c = $this->go($c, 'REJECTED');
        $this->assertSame(['0.00', '100.00'], [(string) $c->approved_amount, (string) $c->rejected_amount]);
        $c = $this->go($c, 'CLOSED');
        $this->assertGreaterThanOrEqual(6, $c->events()->count());
        $this->expectException(ValidationException::class);
        $this->go($c, 'APPEAL');
    }

    public function test_approved_money_is_recovery_until_the_payout_arrives_never_twice(): void
    {
        $o = $this->order();
        $this->assertSame('100.00', (string) OrderProfit::first()->estimated_profit);
        $c = $this->go($this->go($this->claim($o, '40'), 'SUBMITTED'), 'APPROVED', ['approved_amount' => '40']);
        $this->assertSame(['140.00', '40.00'], [(string) OrderProfit::first()->estimated_profit, (string) OrderProfit::first()->recovery]);

        $this->item(['order_id' => $o->id, 'kind' => 'SALE', 'amount' => '236.00']);
        $this->item(['order_id' => $o->id, 'kind' => 'REIMBURSEMENT', 'amount' => '40.00']);   // the real payout
        app(ProfitService::class)->recalcOrder($o);
        $p = OrderProfit::first();
        $this->assertSame(['140.00', '40.00', 'ACTUAL'], [(string) $p->actual_profit, (string) $p->recovery, $p->profit_status]);   // 140, not 180
    }

    public function test_deadline_alerts_use_the_configured_windows(): void
    {
        $mk = function (int $days, string $status = 'DRAFT') { static $n = 0; return Claim::create(['claim_number' => 'X' . ++$n, 'marketplace_id' => $this->mp->id, 'claim_type' => 'DAMAGED_ITEM',
            'status' => $status, 'claim_amount' => '10', 'deadline' => now()->addDays($days)->toDateString()]); };
        $this->assertSame('URGENT', $mk(2)->deadlineState());
        $this->assertSame('WARNING', $mk(6)->deadlineState());
        $this->assertSame('OK', $mk(20)->deadlineState());
        $this->assertSame('EXPIRED', $mk(-1)->deadlineState());
        $this->assertNull($mk(-5, 'SUBMITTED')->deadlineState());                // already filed: nothing for you to do
        $this->assertSame(2, Claim::urgentCount());                              // the urgent one + the expired one
    }

    public function test_approved_claim_that_never_gets_paid_is_flagged_then_clears(): void
    {
        $o = $this->order();
        Claim::create(['claim_number' => 'CL-T-1', 'marketplace_id' => $this->mp->id, 'order_id' => $o->id, 'claim_type' => 'SAFE_T', 'status' => 'APPROVED',
            'claim_amount' => '40', 'approved_amount' => '40', 'decision_date' => now()->subDays(30)->toDateString()]);
        app(ReconciliationService::class)->run();
        $this->assertSame(1, ReconciliationIssue::where('issue_type', 'CLAIM_UNPAID')->where('status', 'OPEN')->count());

        $this->item(['order_id' => $o->id, 'kind' => 'REIMBURSEMENT', 'amount' => '40.00']);
        app(ReconciliationService::class)->run();
        $this->assertSame('RESOLVED', ReconciliationIssue::where('issue_type', 'CLAIM_UNPAID')->first()->status);
    }

    public function test_return_evidence_is_copied_into_the_claim(): void
    {
        Storage::fake('local');
        $o = $this->order();
        $ret = app(ReturnService::class)->create($o, ['return_type' => 'DAMAGED', 'return_date' => now()->toDateString()], [$o->items->first()->id => 1], $this->u);
        Storage::disk('local')->put("returns/{$ret->id}/a.jpg", 'img');
        ReturnMedia::create(['return_id' => $ret->id, 'path' => "returns/{$ret->id}/a.jpg", 'original_name' => 'a.jpg', 'mime' => 'image/jpeg', 'size' => 3]);
        $c = app(ClaimService::class)->create(['marketplace_id' => $this->mp->id, 'return_id' => $ret->id, 'claim_type' => 'SAFE_T', 'claim_amount' => '118'], $this->u);

        $this->assertSame(1, app(ClaimService::class)->attachReturnEvidence($c, $ret->fresh(), $this->u));
        $doc = ClaimDocument::first();
        $this->assertTrue(Storage::disk('local')->exists($doc->path));
        $this->assertTrue(Storage::disk('local')->exists("returns/{$ret->id}/a.jpg"));          // the return keeps its own copy
    }
}
