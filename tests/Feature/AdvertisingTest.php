<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{AdvertisingDaily, DataImport, Marketplace, Order, OrderProfit, Product, ProductCostHistory, User};
use App\Services\{AdsService, ImportProcessor, InventoryService, OrderService, ProfitService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvertisingTest extends TestCase
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
        app(InventoryService::class)->record($this->p, 'STOCK_IN', 50);   // no fee rules: order profit = 200 revenue - 100 cogs = 100.00
    }

    private function day(): string { return now()->subDays(3)->toDateString(); }
    private function order(string $ext): Order
    {
        return app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => $ext, 'order_date' => now()->subDays(3)],
            [['product_id' => $this->p->id, 'quantity' => 2, 'unit_price' => '118']], $this->u);
    }
    private function import(string $type, string $csv, array $map): DataImport
    {
        Storage::fake('local'); Storage::disk('local')->put('imports/a.csv', $csv);
        return DataImport::create(['marketplace_id' => $this->mp->id, 'data_type' => $type, 'file_name' => 'a.csv', 'stored_path' => 'imports/a.csv', 'file_hash' => str_repeat('c', 64),
            'status' => 'MAPPED', 'mapping' => $map, 'uploaded_by' => $this->u->id, 'options' => ['date_format' => 'auto']]);
    }
    private const M = ['date' => 'date', 'campaign' => 'campaign', 'impressions' => 'imp', 'clicks' => 'clicks', 'spend' => 'spend', 'sales' => 'sales', 'orders' => 'orders'];
    private function run(DataImport $i): array { return app(ImportProcessor::class)->run($i, true); }
    private function profits(): array { return OrderProfit::orderBy('id')->get()->map(fn ($r) => [(string) $r->ads_cost, (string) $r->estimated_profit])->all(); }

    public function test_product_ad_spend_lands_on_that_products_orders_exactly_and_updates_on_revision(): void
    {
        $this->order('A1'); $this->order('A2');
        $csv = fn ($spend) => "date,campaign,sku,imp,clicks,spend,sales,orders\n{$this->day()},C1,SKU1,1000,50,$spend,236,1\n";
        $map = self::M + ['sku' => 'sku'];

        $imp = $this->import('ADS_PRODUCT', $csv('40.01'), $map);
        $this->assertSame(1, $this->run($imp)['created']);
        $ads = collect($this->profits())->pluck(0)->map(fn ($v) => (int) round($v * 100));
        $this->assertSame(4001, $ads->sum());                       // split across the two orders, total exact to the paisa
        $this->assertSame(15999, (int) round(OrderProfit::sum('estimated_profit') * 100));   // 2 x 100.00 profit - 40.01 ads

        $imp->update(['status' => 'MAPPED']);                       // same file again
        $this->assertSame(1, $this->run($imp)['duplicate']);
        $this->assertSame(1, AdvertisingDaily::count());

        $rev = $this->import('ADS_PRODUCT', $csv('60.00'), $map);   // marketplace revised the numbers
        $s = $this->run($rev);
        $this->assertSame([1, 0], [$s['created'], $s['duplicate']]);
        $this->assertSame(1, AdvertisingDaily::count());            // updated in place
        $this->assertSame(6000, collect($this->profits())->pluck(0)->map(fn ($v) => (int) round($v * 100))->sum());
        $this->assertSame(10000 * 2 - 6000, (int) round(OrderProfit::sum('estimated_profit') * 100));
    }

    public function test_orders_created_after_the_ad_report_still_get_their_share(): void
    {
        $csv = "date,campaign,sku,imp,clicks,spend,sales,orders\n{$this->day()},C1,SKU1,100,10,30.00,0,0\n";
        $this->run($this->import('ADS_PRODUCT', $csv, self::M + ['sku' => 'sku']));   // spend, but no order that day yet
        $this->assertSame(0, OrderProfit::count());
        $this->order('LATE1');
        $this->assertSame('30.00', (string) OrderProfit::first()->ads_cost);
        $this->assertSame('70.00', (string) OrderProfit::first()->estimated_profit);
    }

    public function test_totals_never_double_count_and_unassigned_spend_is_reported(): void
    {
        $this->order('A1');
        $h = "date,campaign,sku,imp,clicks,spend,sales,orders\n";
        $this->run($this->import('ADS_CAMPAIGN', $h . "{$this->day()},C1,,1000,50,100.00,500,3\n", self::M));
        $this->run($this->import('ADS_PRODUCT', $h . "{$this->day()},C1,SKU1,1000,50,40.00,236,1\n", self::M + ['sku' => 'sku']));
        $f = ['from' => $this->day(), 'to' => $this->day()];
        $this->assertSame(100.0, app(AdsService::class)->totalSpend($f['from'], $f['to']));       // campaign report wins, not 140
        $r = app(ProfitService::class)->report($f);
        $this->assertSame([100.0, 60.0], [$r['adsTotal'], $r['adsUnallocated']]);                  // 40 sits on the product, 60 has no sale to carry it
    }

    public function test_keyword_intelligence_finds_wasted_winners_and_low_conversion(): void
    {
        $h = "date,campaign,group,keyword,match,imp,clicks,spend,sales,orders\n"; $d = $this->day();
        $csv = $h . "$d,C1,G1,wasted kw,broad,900,40,300.00,0,0\n$d,C1,G1,winner kw,exact,500,30,20.00,200.00,2\n$d,C1,G1,meh kw,phrase,3000,60,50.00,120.00,1\n";
        $map = ['date' => 'date', 'campaign' => 'campaign', 'ad_group' => 'group', 'keyword' => 'keyword', 'match_type' => 'match', 'impressions' => 'imp', 'clicks' => 'clicks', 'spend' => 'spend', 'sales' => 'sales', 'orders' => 'orders'];
        $this->run($this->import('ADS_KEYWORD', $csv, $map));
        $i = app(AdsService::class)->insights(['from' => $d, 'to' => $d]);
        $L = $i['levels']['KEYWORD'];
        $this->assertSame(['wasted kw'], $L['wasted']->pluck('label')->all());
        $this->assertSame(['winner kw'], $L['winners']->pluck('label')->all());                    // ACOS 10% <= 25%
        $this->assertSame(['meh kw'], $L['potential']->pluck('label')->all());                     // 60 clicks, 1.67% conversion, ACOS 41.7%
    }

    public function test_bad_rows_are_reported_not_guessed(): void
    {
        $h = "date,campaign,sku,imp,clicks,spend,sales,orders\n"; $d = $this->day();
        $csv = $h . "$d,C1,NOPE,10,1,5.00,0,0\n$d,C2,SKU1,10,1,5.00,0,0\n$d,C2,SKU1,10,1,6.00,0,0\nnot-a-date,C3,SKU1,1,1,1.00,0,0\n";
        $s = $this->run($this->import('ADS_PRODUCT', $csv, self::M + ['sku' => 'sku']));
        $this->assertSame([1, 3], [$s['ok'], $s['error']]);       // unknown SKU, repeated key, bad date
    }
}
