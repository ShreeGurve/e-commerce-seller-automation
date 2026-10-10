<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{DataImport, FeeRule, Marketplace, Order, OrderProfit, Product, ProductCostHistory, ReconciliationIssue, SettlementItem, User};
use App\Services\{ImportProcessor, InventoryService, OrderService, ReconciliationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettlementReconTest extends TestCase
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
        app(InventoryService::class)->record($this->p, 'STOCK_IN', 50);
        FeeRule::create(['marketplace_id' => $this->mp->id, 'fee_type' => 'REFERRAL', 'percentage' => 10, 'fixed_amount' => 0, 'effective_from' => '2026-01-01']);
        FeeRule::create(['marketplace_id' => $this->mp->id, 'fee_type' => 'CLOSING', 'percentage' => 0, 'fixed_amount' => 10, 'effective_from' => '2026-01-01']);
    }

    /** 2 units x Rs 118 = 236.00 (GST 36.00 inside) */
    private function order(string $ext = 'A1', int $daysAgo = 3): Order
    {
        return app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => $ext, 'order_date' => now()->subDays($daysAgo)],
            [['product_id' => $this->p->id, 'quantity' => 2, 'unit_price' => '118']], $this->u);
    }
    private function ship(Order $o): void { foreach (['PACKED', 'SHIPPED'] as $s) app(OrderService::class)->changeStatus($o, $s); }
    private function d(): string { return now()->subDay()->toDateString(); }

    private function import(string $csv, array $map, array $opts = []): DataImport
    {
        Storage::fake('local'); Storage::disk('local')->put('imports/s.csv', $csv);
        return DataImport::create(['marketplace_id' => $this->mp->id, 'data_type' => 'SETTLEMENTS', 'file_name' => 's.csv', 'stored_path' => 'imports/s.csv', 'file_hash' => str_repeat('b', 64),
            'status' => 'MAPPED', 'mapping' => $map, 'uploaded_by' => $this->u->id, 'options' => $opts + ['date_format' => 'auto', 'deductions_positive' => true]]);
    }
    private const LONG = ['external_settlement_id' => 'settlement-id', 'posted_date' => 'posted-date', 'external_order_id' => 'order-id', 'sku' => 'sku',
        'txn_type' => 'transaction-type', 'amount_type' => 'amount-type', 'amount_desc' => 'amount-description', 'amount' => 'amount'];
    private function longCsv(string $sid, string $oid, string $commission = '-25.00'): string
    {
        $d = $this->d(); $h = 'settlement-id,posted-date,order-id,sku,transaction-type,amount-type,amount-description,amount';
        return "$h\n$sid,$d,$oid,SKU1,Order,ItemPrice,Principal,236.00\n$sid,$d,$oid,SKU1,Order,ItemFees,Commission,$commission\n"
             . "$sid,$d,$oid,SKU1,Order,ItemFees,FixedClosingFee,-20.00\n$sid,$d,$oid,SKU1,Order,ItemFees,Tax on Commission,-4.50\n";
    }

    public function test_long_format_gives_actual_profit_is_idempotent_and_matches(): void
    {
        $this->order('A1');
        $imp = $this->import($this->longCsv('S1', 'A1'), self::LONG);
        $s = app(ImportProcessor::class)->run($imp, true);
        $this->assertSame([1, 0], [$s['created'], $s['error']]);

        $pr = OrderProfit::first();
        $this->assertSame('ACTUAL', $pr->profit_status);
        $this->assertSame('50.50', (string) $pr->actual_profit);       // 200 - 100 - (25 + 20 + 4.50)
        $this->assertSame('48.55', (string) $pr->estimated_profit);    // estimate kept for comparison
        $this->assertSame('186.50', (string) \App\Models\Settlement::first()->total_amount);
        $this->assertSame('MATCHED', Order::first()->recon_status);
        $this->assertSame(0, ReconciliationIssue::count());

        $imp->update(['status' => 'MAPPED']);                          // same file again
        $this->assertSame(4, app(ImportProcessor::class)->run($imp, true)['duplicate']);
        $this->assertSame(4, SettlementItem::count());
    }

    public function test_one_column_per_fee_format_with_positive_deductions(): void
    {
        $this->order('A1');
        $csv = "settlement,date,order,sku,sale,commission,fixed,gst\nS2," . $this->d() . ",A1,SKU1,236,25,20,4.5\n";
        $imp = $this->import($csv, ['external_settlement_id' => 'settlement', 'posted_date' => 'date', 'external_order_id' => 'order', 'sku' => 'sku',
            'sale_amount' => 'sale', 'referral_fee' => 'commission', 'closing_fee' => 'fixed', 'fee_tax' => 'gst']);
        app(ImportProcessor::class)->run($imp, true);
        $this->assertSame('50.50', (string) OrderProfit::first()->actual_profit);
        $this->assertSame('-25.00', (string) SettlementItem::where('kind', 'REFERRAL_FEE')->value('amount'));   // stored as money OUT
    }

    public function test_payout_arriving_before_its_order_is_linked_later(): void
    {
        $imp = $this->import($this->longCsv('S3', 'ZZ9'), self::LONG);
        app(ImportProcessor::class)->run($imp, true);
        $this->assertSame(1, ReconciliationIssue::where('issue_type', 'UNKNOWN_ORDER')->where('status', 'OPEN')->count());

        $this->order('ZZ9');                                            // the order shows up afterwards
        app(ReconciliationService::class)->run();
        $this->assertSame('RESOLVED', ReconciliationIssue::where('issue_type', 'UNKNOWN_ORDER')->first()->status);
        $this->assertSame(0, SettlementItem::whereNull('order_id')->count());
        $this->assertNotNull(OrderProfit::first()->actual_profit);
    }

    public function test_missing_settlement_and_unexpected_fee_are_flagged_and_ignore_sticks(): void
    {
        $old = $this->order('OLD1', 40); $this->ship($old);             // shipped 40 days ago, never paid
        $b = $this->order('B1');
        app(ImportProcessor::class)->run($this->import($this->longCsv('S4', 'B1', '-200.00'), self::LONG), true);   // fee far above your rules

        app(ReconciliationService::class)->run();
        $this->assertSame(1, ReconciliationIssue::where('issue_type', 'MISSING_SETTLEMENT')->where('order_id', $old->id)->count());
        $this->assertSame(1, ReconciliationIssue::where('issue_type', 'FEE_MISMATCH')->where('order_id', $b->id)->count());
        $this->assertSame('MISMATCH', Order::find($b->id)->recon_status);

        $issue = ReconciliationIssue::where('issue_type', 'MISSING_SETTLEMENT')->first();
        $issue->update(['status' => 'IGNORED']);
        app(ReconciliationService::class)->run();
        $this->assertSame('IGNORED', $issue->fresh()->status);          // stays ignored
        $this->assertSame(2, ReconciliationIssue::count());             // no duplicates created
    }

    public function test_settlement_line_classifier(): void
    {
        $a = new AmazonAdapter($this->mp);
        foreach ([['Order', 'ItemFees', 'Commission', 'REFERRAL_FEE'], ['Order', 'ItemFees', 'FixedClosingFee', 'CLOSING_FEE'], ['Order', 'ItemFees', 'FBAPerUnitFulfillmentFee', 'SHIPPING_FEE'],
            ['Order', 'ItemFees', 'Tax on Commission', 'FEE_TAX'], ['Order', 'ItemPrice', 'Principal', 'SALE'], ['Order', 'ItemPrice', 'Tax', 'TAX'],
            ['Order', 'ItemPrice', 'Shipping', 'SHIPPING_CREDIT'], ['Refund', 'ItemPrice', 'Principal', 'REFUND'], ['Other', 'Other', 'SAFE-T reimbursement', 'REIMBURSEMENT'],
            ['Order', 'Promotion', 'Principal', 'ADJUSTMENT']] as [$t, $at, $desc, $want]) {
            $this->assertSame($want, $a->classifySettlementLine($t, $at, $desc), "$t/$at/$desc");
        }
    }
}
