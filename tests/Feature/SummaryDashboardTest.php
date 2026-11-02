<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{Claim, DataImport, Inventory, Marketplace, Order, Product, ProductCostHistory, Role, User};
use App\Services\{DashboardService, DecisionService, ImportProcessor, InventoryService, OrderService, SummaryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class SummaryDashboardTest extends TestCase
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
        $this->u->roles()->attach(Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_system' => true]));   // may open everything
        $this->mp = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon', 'adapter_class' => AmazonAdapter::class]);
        $this->p = Product::create(['sku' => 'SKU1', 'name' => 'P1', 'gst_rate' => 18]);
        ProductCostHistory::create(['product_id' => $this->p->id, 'cost_type' => 'COGS', 'amount' => '50', 'effective_from' => '2026-01-01']);
        app(InventoryService::class)->record($this->p, 'STOCK_IN', 50);   // no fee rules: one order of 2 units = revenue 200, cost 100, profit 100
    }

    private function day(int $ago = 3): string { return now()->subDays($ago)->toDateString(); }
    private function order(string $ext, int $ago = 3): Order
    {
        return app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => $ext, 'order_date' => now()->subDays($ago)],
            [['product_id' => $this->p->id, 'quantity' => 2, 'unit_price' => '118']], $this->u);
    }
    private function row(string $t, ?string $d = null) { return DB::table($t)->where('date', $d ?? $this->day())->first(); }

    public function test_speed_tables_follow_orders_and_cancellations_automatically(): void
    {
        $o = $this->order('A1');
        $s = $this->row('daily_sales'); $p = $this->row('daily_profit');
        $this->assertSame([1, 2], [(int) $s->orders, (int) $s->units]);
        $this->assertEqualsWithDelta(236.0, (float) $s->gross_sales, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $p->revenue, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $p->profit, 0.001);

        app(OrderService::class)->changeStatus($o, 'CANCELLED');
        $s = $this->row('daily_sales');
        $this->assertSame([0, 1], [(int) $s->orders, (int) $s->cancelled_orders]);
        $this->assertNull($this->row('daily_profit'));                                           // an all-zero day has no row
    }

    public function test_rebuilding_from_scratch_gives_exactly_the_same_numbers(): void
    {
        $this->order('A1'); $this->order('A2', 5);
        $before = [DB::table('daily_sales')->orderBy('date')->get(['date', 'orders', 'units', 'gross_sales'])->toArray(), DB::table('daily_profit')->orderBy('date')->get(['date', 'revenue', 'profit'])->toArray()];
        DB::table('daily_sales')->delete(); DB::table('daily_profit')->delete();
        $this->assertGreaterThanOrEqual(2, app(SummaryService::class)->rebuildAll());
        $after = [DB::table('daily_sales')->orderBy('date')->get(['date', 'orders', 'units', 'gross_sales'])->toArray(), DB::table('daily_profit')->orderBy('date')->get(['date', 'revenue', 'profit'])->toArray()];
        $this->assertEquals($before, $after);
    }

    public function test_batching_refreshes_each_day_once_at_the_end(): void
    {
        $s = app(SummaryService::class);
        $s->begin();
        $this->order('B1'); $this->order('B2'); $this->order('B3');
        $this->assertSame(0, DB::table('daily_sales')->count());                                 // nothing written while batching
        $this->assertSame(1, $s->flush());                                                       // 3 orders, one day, one refresh
        $r = $this->row('daily_sales');
        $this->assertSame([3, 6], [(int) $r->orders, (int) $r->units]);
    }

    private function adsImport(string $type, string $csv, array $map): void
    {
        Storage::fake('local'); Storage::disk('local')->put('imports/a.csv', $csv);
        $i = DataImport::create(['marketplace_id' => $this->mp->id, 'data_type' => $type, 'file_name' => 'a.csv', 'stored_path' => 'imports/a.csv', 'file_hash' => str_repeat('f', 64),
            'status' => 'MAPPED', 'mapping' => $map, 'uploaded_by' => $this->u->id, 'options' => ['date_format' => 'auto']]);
        app(ImportProcessor::class)->run($i, true);
    }

    public function test_ad_spend_flows_into_the_dashboard_and_unassigned_spend_is_a_leak(): void
    {
        $this->order('A1'); $d = $this->day(); $h = "date,campaign,sku,imp,clicks,spend,sales,orders\n";
        $m = ['date' => 'date', 'campaign' => 'campaign', 'impressions' => 'imp', 'clicks' => 'clicks', 'spend' => 'spend', 'sales' => 'sales', 'orders' => 'orders'];
        $this->adsImport('ADS_PRODUCT', $h . "$d,C1,SKU1,1000,50,40.00,236,1\n", $m + ['sku' => 'sku']);
        $this->assertEqualsWithDelta(40.0, (float) $this->row('daily_advertising')->spend, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $this->row('daily_profit')->ads, 0.001);
        $k = app(DashboardService::class)->build(['from' => $d, 'to' => $d])['kpi'];
        $this->assertEqualsWithDelta(60.0, $k['orderProfit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $k['unallocated'], 0.001);

        $this->adsImport('ADS_CAMPAIGN', $h . "$d,C1,,1000,50,100.00,500,3\n", $m);              // the campaign report says Rs100 was really spent
        $k = app(DashboardService::class)->build(['from' => $d, 'to' => $d])['kpi'];
        $this->assertEqualsWithDelta(100.0, $k['adSpend'], 0.001);                               // campaign report wins, no double count
        $this->assertEqualsWithDelta(60.0, $k['unallocated'], 0.001);                            // Rs60 no sale could carry
        $this->assertEqualsWithDelta(0.0, $k['net'], 0.001);                                     // 60 order profit - 60 leak
        $this->assertEqualsWithDelta(100 / 500 * 100, $k['acos'], 0.001);
    }

    public function test_decision_list_shows_real_problems_most_urgent_first(): void
    {
        Claim::create(['claim_number' => 'CL-T-1', 'marketplace_id' => $this->mp->id, 'claim_type' => 'SAFE_T', 'status' => 'DRAFT', 'claim_amount' => '100', 'deadline' => now()->addDay()->toDateString()]);
        Inventory::where('product_id', $this->p->id)->update(['minimum_stock' => 100]);          // 50 in stock, wants 100
        $titles = array_column(app(DecisionService::class)->actions($this->u), 'title');
        $this->assertStringContainsString('claim', $titles[0]);                                  // severity 3 first
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'below minimum stock')));
    }

    public function test_dashboard_page_renders_with_and_without_data(): void
    {
        $this->actingAs($this->u)->get(route('dashboard'))->assertOk()->assertSee('What should I do today?');   // empty business
        $this->order('A1');
        $this->actingAs($this->u)->get(route('dashboard'))->assertOk()->assertSee('Where the money went')->assertSee('₹236');
    }
}
