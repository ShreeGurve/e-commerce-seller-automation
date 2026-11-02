<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{AdvertisingCampaign, AdvertisingDaily, ExpenseCategory, Expense, Marketplace, Order, Permission, Product, ProductCostHistory, Purchase, PurchaseItem, Role, Settlement, SettlementItem, Supplier, User};
use App\Services\{ClaimService, InventoryService, OrderService, ReportService, ReturnService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $u; private Product $p; private Marketplace $mp; private string $day;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the database. Only run on in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
        $this->day = now()->subDays(3)->toDateString();
        $this->u = User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']);
        $this->u->roles()->attach(Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_system' => true]));
        $this->mp = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon', 'adapter_class' => AmazonAdapter::class]);
        $this->p = $this->product('SKU1', '50');
        app(InventoryService::class)->record($this->p, 'STOCK_IN', 50);
    }

    private function product(string $sku, string $cogs): Product
    {
        $p = Product::create(['sku' => $sku, 'name' => "Product {$sku}", 'gst_rate' => 18]);
        ProductCostHistory::create(['product_id' => $p->id, 'cost_type' => 'COGS', 'amount' => $cogs, 'effective_from' => '2026-01-01']);
        return $p;
    }
    private function order(Product $p, string $ext): Order
    {
        $o = app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => $ext, 'order_date' => now()->subDays(3)], [['product_id' => $p->id, 'quantity' => 2, 'unit_price' => '118']], $this->u);
        foreach (['PACKED', 'SHIPPED'] as $s) app(OrderService::class)->changeStatus($o, $s);
        return $o->fresh();
    }
    private function f(): array { return ['from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString(), 'marketplace_id' => null, 'product_id' => null, 'category_id' => null, 'sort' => 'profit_desc']; }

    /** one row in every table every report reads */
    private function seedEverything(): Order
    {
        $o = $this->order($this->p, 'A1');
        $rs = app(ReturnService::class);
        $ret = $rs->create($o, ['return_type' => 'DAMAGED', 'return_date' => now()->toDateString()], [$o->items->first()->id => 1], $this->u);
        $rs->inspect($ret, [$ret->items->first()->id => ['received_qty' => 1, 'condition' => 'DAMAGED']], true, null, $this->u);
        app(ClaimService::class)->create(app(ClaimService::class)->suggestFromReturn($ret->fresh()), $this->u);
        $pu = Purchase::create(['supplier_id' => Supplier::create(['name' => 'Sup'])->id, 'po_number' => 'PO-T-1', 'purchase_date' => $this->day, 'status' => 'RECEIVED', 'payment_status' => 'UNPAID', 'subtotal' => '1000', 'gst_amount' => '180', 'total_amount' => '1180']);
        PurchaseItem::create(['purchase_id' => $pu->id, 'product_id' => $this->p->id, 'quantity' => 10, 'unit_cost' => '100', 'gst_rate' => '18', 'line_subtotal' => '1000', 'gst_amount' => '180', 'line_total' => '1180']);
        Expense::create(['expense_category_id' => ExpenseCategory::first()->id, 'expense_date' => $this->day, 'amount' => '500', 'notes' => '=HYPERLINK("http://evil","x")']);
        $st = Settlement::create(['marketplace_id' => $this->mp->id, 'external_settlement_id' => 'S1', 'period_start' => $this->day, 'period_end' => $this->day]);
        foreach ([['SALE', '236.00'], ['REFERRAL_FEE', '-25.00']] as $i => [$k, $a]) SettlementItem::create(['settlement_id' => $st->id, 'order_id' => $o->id, 'kind' => $k, 'amount' => $a, 'posted_date' => $this->day, 'row_hash' => sha1("x{$i}")]);
        $c = AdvertisingCampaign::create(['marketplace_id' => $this->mp->id, 'name' => 'Camp1']);
        AdvertisingDaily::create(['marketplace_id' => $this->mp->id, 'date' => $this->day, 'level' => 'CAMPAIGN', 'campaign_id' => $c->id, 'impressions' => 1000, 'clicks' => 50, 'spend' => '40', 'sales' => '236', 'orders' => 1, 'units' => 2, 'row_key' => sha1('ad')]);
        return $o;
    }

    public function test_every_report_runs_and_each_row_matches_its_columns(): void
    {
        $this->seedEverything();
        $svc = app(ReportService::class);
        $this->assertCount(13, $svc->catalog());
        foreach (array_keys($svc->catalog()) as $key) {
            $d = $svc->run($key, $this->f());
            $this->assertNotEmpty($d['columns'], $key);
            $this->assertNotEmpty($d['rows'], "{$key} should have data in this scenario");
            foreach ($d['rows'] as $r) foreach ($d['columns'] as [$k]) $this->assertArrayHasKey($k, $r, "{$key} row is missing column {$k}");
        }
        $sales = $svc->run('sales', $this->f());
        $this->assertSame([1, 2], [$sales['rows'][0]['orders'], $sales['rows'][0]['units']]);
        $this->assertEqualsWithDelta(236.0, $sales['totals']['sales'], 0.001);
    }

    public function test_product_profitability_flags_losses_and_totals_add_up(): void
    {
        $this->order($this->p, 'A1');
        $this->order($this->product('LOSS1', '500'), 'A2');          // sells at 200 ex-GST but costs 1000: a loss
        $d = app(ReportService::class)->run('product_profitability', $this->f());
        $by = collect($d['rows'])->keyBy('sku');
        $this->assertStringContainsString('LOSS', $by['LOSS1']['flags']);
        $this->assertStringContainsString('TOP PROFIT', $by['SKU1']['flags']);
        $this->assertSame('LOSS1', $d['rows'][count($d['rows']) - 1]['sku']);                    // worst last when sorted by highest profit
        $this->assertEqualsWithDelta(array_sum(array_column($d['rows'], 'profit')), $d['totals']['profit'], 0.01);
        $asc = app(ReportService::class)->run('product_profitability', ['sort' => 'profit_asc'] + $this->f());
        $this->assertSame('LOSS1', $asc['rows'][0]['sku']);
    }

    public function test_gst_support_report_arithmetic(): void
    {
        $this->seedEverything();
        $row = collect(app(ReportService::class)->run('gst', $this->f())['rows'])->firstWhere('rate', '18%');
        $this->assertEqualsWithDelta(200.0, $row['taxable_sales'], 0.01);          // 236 incl. GST
        $this->assertEqualsWithDelta(36.0, $row['output_gst'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $row['taxable_purchases'], 0.01);
        $this->assertEqualsWithDelta(180.0, $row['input_gst'], 0.01);
        $this->assertEqualsWithDelta(-144.0, $row['net_gst'], 0.01);
    }

    public function test_csv_export_is_clean_and_blocks_spreadsheet_formulas(): void
    {
        $this->seedEverything();
        $r = $this->actingAs($this->u)->get(route('reports.export', ['expenses', 'csv']));
        $r->assertOk();
        $csv = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);                                       // Excel-friendly UTF-8
        $this->assertStringContainsString('Date,Category,Channel,Product,Invoice,Notes,Amount', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);                                    // formula neutralised
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('500.00', $csv);                                         // plain number, no rupee sign or commas
        $this->assertStringContainsString('TOTAL', $csv);
    }

    public function test_excel_export_is_a_real_workbook_with_the_data(): void
    {
        $this->seedEverything();
        $r = $this->actingAs($this->u)->get(route('reports.export', ['sales', 'xlsx']));
        $r->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $r->headers->get('Content-Type'));
        $bin = $r->getContent(); $this->assertStringStartsWith('PK', $bin);
        $tmp = tempnam(sys_get_temp_dir(), 'x'); file_put_contents($tmp, $bin);
        $zip = new \ZipArchive(); $this->assertTrue($zip->open($tmp));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Sales (incl. GST)', $sheet); $this->assertStringContainsString('<v>236</v>', $sheet);
        $zip->close(); @unlink($tmp);
    }

    public function test_viewing_is_separate_from_exporting(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v@test.local', 'password' => 'x']);
        $role = Role::create(['name' => 'Report viewer', 'slug' => 'report-viewer']);
        $role->permissions()->attach(Permission::forceCreate(['name' => 'reports.view', 'module' => 'reports']));
        $viewer->roles()->attach($role);
        $this->actingAs($viewer)->get(route('reports.show', 'sales'))->assertOk();
        $this->actingAs($viewer)->get(route('reports.export', ['sales', 'csv']))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.export', ['sales', 'xlsx']))->assertForbidden();
    }

    public function test_every_report_page_the_hub_the_print_view_and_the_daily_report_render(): void
    {
        $this->seedEverything();
        $this->actingAs($this->u)->get(route('reports.index'))->assertOk()->assertSee('Daily business report');
        foreach (array_keys(app(ReportService::class)->catalog()) as $key) {
            $this->actingAs($this->u)->get(route('reports.show', $key))->assertOk();
            $this->actingAs($this->u)->get(route('reports.print', $key))->assertOk()->assertSee('SHREE GURVE');
        }
        $this->actingAs($this->u)->get(route('reports.daily', ['date' => $this->day]))->assertOk()->assertSee('Top 10 products')->assertSee('Important alerts');
    }
}
