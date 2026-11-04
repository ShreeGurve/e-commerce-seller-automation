<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{CompetitorPrice, DataImport, FeeRule, Marketplace, MarketplaceProductSetting, OrderProfit, PriceHistory, Product, ProductCostHistory, User};
use App\Services\{ImportProcessor, OrderService, PricingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PricingTest extends TestCase
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
        $this->p = Product::create(['sku' => 'SKU1', 'name' => 'P1', 'gst_rate' => 18, 'default_selling_price' => '300']);
        ProductCostHistory::create(['product_id' => $this->p->id, 'cost_type' => 'COGS', 'amount' => '100', 'effective_from' => '2026-01-01']);
        FeeRule::create(['marketplace_id' => $this->mp->id, 'fee_type' => 'REFERRAL', 'percentage' => 10, 'fixed_amount' => 0, 'applies_on' => 'PRICE', 'effective_from' => '2026-01-01']);
        FeeRule::create(['marketplace_id' => $this->mp->id, 'fee_type' => 'CLOSING', 'percentage' => 0, 'fixed_amount' => 10, 'effective_from' => '2026-01-01']);
    }

    private function a(?string $price = null): array { return app(PricingService::class)->analyze($this->p->fresh(), $this->mp, $price); }

    public function test_break_even_minimum_and_target_prices_come_from_the_real_fee_rules(): void
    {
        $a = $this->a();
        // 10% referral + Rs10 fixed, 18% GST on fees, 18% GST on price, cost Rs100 (worked out by hand)
        $this->assertSame([15400, 16300, 20000], [$a['break_even'], $a['min_price'], $a['target_price']]);
        $this->assertSame('OK', $a['status']);
        $this->assertSame(42, (int) round($a['at']['margin']));
        $this->assertSame(45.7, $a['max_discount']);
        $this->assertFalse($a['no_fees']);
    }

    public function test_channel_manual_price_is_used_until_a_recorded_price_exists(): void
    {
        MarketplaceProductSetting::create([
            'product_id' => $this->p->id, 'marketplace_id' => $this->mp->id,
            'outbound_shipping_cost' => '0.00', 'handling_cost' => '0.00',
            'desired_profit_min' => '60.00', 'desired_profit_max' => '80.00',
            'manual_selling_price' => '275.00', 'auto_price_enabled' => false, 'push_enabled' => false,
        ]);
        $current = app(PricingService::class)->currentPrice($this->p->fresh(), $this->mp->id);
        $this->assertSame('275.00', $current['price']);
        $this->assertSame('Channel manual selling price override', $current['source']);
    }

    public function test_channel_shipping_and_absolute_profit_targets_are_used_in_recommendation(): void
    {
        MarketplaceProductSetting::create([
            'product_id' => $this->p->id, 'marketplace_id' => $this->mp->id,
            'outbound_shipping_cost' => '50.00', 'handling_cost' => '10.00',
            'desired_profit_min' => '60.00', 'desired_profit_max' => '80.00',
            'auto_price_enabled' => true, 'push_enabled' => false,
        ]);

        $a = $this->a();
        $this->assertSame('absolute', $a['profit_target_mode']);
        $this->assertSame(5000, $a['channel_shipping']);
        $this->assertSame(1000, $a['channel_handling']);
        $this->assertGreaterThan(15400, $a['break_even']);
        $this->assertGreaterThan($a['break_even'], $a['min_price']);
        $this->assertGreaterThan($a['min_price'], $a['target_price']);
        $this->assertSame('BELOW_MIN', $a['status']);
    }

    public function test_recorded_prices_change_the_status_and_keep_a_history(): void
    {
        $svc = app(PricingService::class);
        $this->assertSame('300.00', $svc->currentPrice($this->p, $this->mp->id)['price']);          // master price until you record one
        foreach ([['150', 'LOSS'], ['160', 'BELOW_MIN'], ['180', 'BELOW_TARGET'], ['250', 'OK']] as [$price, $status]) {
            $svc->recordPrice($this->p, $this->mp->id, $price, 'test', null, $this->u);
            $this->assertSame($status, $this->a()['status'], "price {$price}");
        }
        $rows = PriceHistory::orderBy('id')->get();
        $this->assertSame(4, $rows->count());
        $this->assertSame(['300.00', '150.00', '160.00', '180.00'], $rows->map(fn ($r) => (string) $r->previous_price)->all());   // every change remembers the one before
    }

    public function test_ad_and_return_cost_per_unit_come_from_real_orders_and_raise_the_floor(): void
    {
        $before = $this->a()['break_even'];
        app(OrderService::class)->create(['marketplace_id' => $this->mp->id, 'external_order_id' => 'A1', 'order_date' => now()->subDays(3)],
            [['product_id' => $this->p->id, 'quantity' => 2, 'unit_price' => '300']], $this->u);
        OrderProfit::query()->update(['ads_cost' => '20.00']);
        $a = $this->a();
        $this->assertEqualsWithDelta(1000.0, $a['ads_per_unit'], 0.001);                          // Rs20 over 2 units
        $this->assertEqualsWithDelta(0.0, $a['return_per_unit'], 0.001);                          // no returns yet
        $this->assertGreaterThan($before, $a['break_even']);
        $this->assertSame(2, $a['history_units']);
    }

    public function test_competitor_prices_update_in_place_and_stale_ones_are_ignored(): void
    {
        $svc = app(PricingService::class);
        $svc->addCompetitor($this->p, $this->mp, 'Rival', '250', now()->toDateString(), 'manual', null, $this->u);
        $svc->addCompetitor($this->p, $this->mp, 'Rival', '240', now()->toDateString(), 'manual', null, $this->u);   // same day: updated, not duplicated
        $svc->addCompetitor($this->p, $this->mp, 'Old seller', '100', now()->subDays(60)->toDateString(), null, null, $this->u);
        $this->assertSame(2, CompetitorPrice::count());
        $a = $this->a();
        $this->assertSame(240.0, $a['competitor_low']);                                            // the 60-day-old price is outside the freshness window
        $this->assertSame(25.0, $a['vs_competitor']);                                              // we are at 300, they at 240
        $this->assertSame('300.00', (string) CompetitorPrice::where('competitor_name', 'Rival')->value('our_price'));
    }

    public function test_competitor_price_import_creates_updates_and_never_duplicates(): void
    {
        $imp = function (string $csv) {
            Storage::fake('local'); Storage::disk('local')->put('imports/c.csv', $csv);
            return DataImport::create(['marketplace_id' => $this->mp->id, 'data_type' => 'COMPETITORS', 'file_name' => 'c.csv', 'stored_path' => 'imports/c.csv', 'file_hash' => str_repeat('e', 64),
                'status' => 'MAPPED', 'uploaded_by' => $this->u->id, 'options' => ['date_format' => 'auto'],
                'mapping' => ['sku' => 'sku', 'competitor' => 'seller', 'competitor_price' => 'price', 'captured_on' => 'date']]);
        };
        $d = now()->subDay()->toDateString();
        $first = $imp("sku,seller,price,date\nSKU1,Rival,250,$d\nSKU1,Other,260,$d\nNOPE,Rival,100,$d\nSKU1,Bad,0,$d\n");
        $s = app(ImportProcessor::class)->run($first, true);
        $this->assertSame([2, 2], [$s['ok'], $s['error']]);                                        // unknown SKU and zero price are reported
        $this->assertSame(2, CompetitorPrice::count());

        $first->update(['status' => 'MAPPED']);
        $this->assertSame(2, app(ImportProcessor::class)->run($first, true)['duplicate']);
        $this->assertSame(2, CompetitorPrice::count());

        $s3 = app(ImportProcessor::class)->run($imp("sku,seller,price,date\nSKU1,Rival,245,$d\n"), true);   // new price, same day = update
        $this->assertSame([1, 0], [$s3['created'], $s3['duplicate']]);
        $this->assertSame('245.00', (string) CompetitorPrice::where('competitor_name', 'Rival')->value('competitor_price'));
    }
}
