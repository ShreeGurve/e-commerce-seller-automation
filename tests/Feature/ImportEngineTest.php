<?php
namespace Tests\Feature;

use App\Models\{DataImport, Inventory, Marketplace, Order, Product, ProductMarketplace, ProductReturn, User};
use App\Services\{ImportProcessor, InventoryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportEngineTest extends TestCase
{
    use RefreshDatabase;

    private ?User $u = null;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the database. Only run on in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
    }

    private function product(string $sku): Product
    {
        $p = Product::create(['sku' => $sku, 'name' => "P $sku", 'gst_rate' => 18]);
        app(InventoryService::class)->record($p, 'STOCK_IN', 10);
        return $p;
    }
    private function avail(string $sku): int { return (int) Inventory::where('product_id', Product::where('sku', $sku)->value('id'))->value('available_qty'); }

    private function import(string $type, string $csv, array $map, array $opts = []): DataImport
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/t.csv', $csv);
        $this->u ??= User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']);
        $mp = Marketplace::firstOrCreate(['code' => 'amazon'], ['name' => 'Amazon', 'adapter_class' => \App\Marketplaces\Amazon\AmazonAdapter::class]);
        return DataImport::create(['marketplace_id' => $mp->id, 'data_type' => $type, 'file_name' => 't.csv', 'stored_path' => 'imports/t.csv',
            'file_hash' => str_repeat('a', 64), 'status' => 'MAPPED', 'mapping' => $map, 'uploaded_by' => $this->u->id,
            'options' => $opts + ['date_format' => 'auto', 'price_mode' => 'unit', 'deduct_stock' => true]]);
    }
    private const OMAP = ['external_order_id' => 'order-id', 'order_date' => 'date', 'sku' => 'sku', 'quantity' => 'qty', 'unit_price' => 'price', 'status' => 'status', 'external_line_id' => 'line'];
    private function d(): string { return now()->subDays(3)->format('Y-m-d\TH:i:sP'); }   // always in the past
    private function r(): string { return now()->subDay()->toDateString(); }

    public function test_orders_group_by_order_skip_bad_orders_and_are_idempotent(): void
    {
        $this->product('SKU1'); $this->product('SKU2');
        $d = $this->d();
        $csv = "order-id,date,sku,qty,price,status,line\nA1,$d,SKU1,1,118,Shipped,L1\nA1,$d,SKU2,2,200,Shipped,L2\n"
             . "B1,$d,SKU1,1,118,Shipped,L3\nB1,$d,NOPE,1,50,Shipped,L4\nC1,$d,SKU1,1,118,Cancelled,L5\n";
        $imp = $this->import('ORDERS', $csv, self::OMAP);

        $s = app(ImportProcessor::class)->run($imp, true);
        $this->assertSame([2, 3, 2], [$s['created'], $s['ok'], $s['error']]);          // A1 + C1 created; B1 (2 rows) rejected whole
        $this->assertSame(2, Order::count());
        $this->assertSame(2, Order::where('external_order_id', 'A1')->first()->items()->count());
        $this->assertSame('CANCELLED', Order::where('external_order_id', 'C1')->value('status'));
        $this->assertSame([9, 8], [$this->avail('SKU1'), $this->avail('SKU2')]);       // cancelled + rejected orders did not touch stock

        $imp->update(['status' => 'MAPPED']);                                          // upload the same data again
        $s2 = app(ImportProcessor::class)->run($imp, true);
        $this->assertSame(3, $s2['duplicate']);
        $this->assertSame(2, Order::count());                                          // nothing duplicated
        $this->assertSame([9, 8], [$this->avail('SKU1'), $this->avail('SKU2')]);
    }

    public function test_dry_run_writes_nothing_and_finished_import_cannot_run_twice(): void
    {
        $this->product('SKU1');
        $imp = $this->import('ORDERS', "order-id,date,sku,qty,price,status,line\nA1," . $this->d() . ",SKU1,1,118,Shipped,L1\n", self::OMAP);
        $s = app(ImportProcessor::class)->run($imp, false);
        $this->assertSame(1, $s['created']);
        $this->assertSame(0, Order::count());

        app(ImportProcessor::class)->run($imp, true);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ImportProcessor::class)->run($imp->fresh(), true);
    }

    public function test_line_total_prices_are_exact_and_stock_deduction_can_be_off(): void
    {
        $this->product('SKU1');
        $imp = $this->import('ORDERS', "order-id,date,sku,qty,price,status,line\nA1," . $this->d() . ",SKU1,3,100.00,Shipped,L1\n", self::OMAP,
            ['price_mode' => 'line', 'deduct_stock' => false]);
        app(ImportProcessor::class)->run($imp, true);
        $o = Order::first();
        $this->assertSame('100.00', (string) $o->total_amount);                         // 3 x 33.33 would be 99.99
        $this->assertSame(10, $this->avail('SKU1'));                                    // history import: stock untouched
    }

    public function test_returns_import_links_to_orders_and_is_idempotent(): void
    {
        $this->product('SKU1');
        $this->import('ORDERS', "order-id,date,sku,qty,price,status,line\nA1," . $this->d() . ",SKU1,2,236,Shipped,L1\n", self::OMAP);
        app(ImportProcessor::class)->run(DataImport::first(), true);

        $imp = $this->import('RETURNS', "return-id,order-id,date,sku,qty,reason\nR1,A1," . $this->r() . ",SKU1,1,Product was damaged\nR2,ZZZ," . $this->r() . ",SKU1,1,x\n",
            ['external_return_id' => 'return-id', 'external_order_id' => 'order-id', 'return_date' => 'date', 'sku' => 'sku', 'quantity' => 'qty', 'reason' => 'reason']);
        $s = app(ImportProcessor::class)->run($imp, true);
        $this->assertSame([1, 1], [$s['created'], $s['error']]);                        // R2: order not found
        $this->assertSame('DAMAGED', ProductReturn::first()->return_type);
        $this->assertSame('RETURNED', Order::first()->status);

        $imp->update(['status' => 'MAPPED']);
        $this->assertSame(1, app(ImportProcessor::class)->run($imp, true)['duplicate']);
        $this->assertSame(1, ProductReturn::count());
    }

    public function test_sku_mapping_import_never_silently_remaps(): void
    {
        $this->product('SKU1'); $this->product('SKU2');
        $map = ['marketplace_sku' => 'm', 'product_sku' => 'p', 'external_product_id' => 'asin'];
        $imp = $this->import('MAPPINGS', "m,p,asin\nAMZ-1,SKU1,B00X\nAMZ-2,SKU2,B00Y\n", $map);
        $this->assertSame(2, app(ImportProcessor::class)->run($imp, true)['created']);

        $imp->update(['status' => 'MAPPED']);
        $this->assertSame(2, app(ImportProcessor::class)->run($imp, true)['duplicate']);

        $imp2 = $this->import('MAPPINGS', "m,p,asin\nAMZ-1,SKU2,B00X\n", $map);
        $s = app(ImportProcessor::class)->run($imp2, true);
        $this->assertSame(1, $s['error']);                                              // already mapped to SKU1
        $this->assertSame(Product::where('sku', 'SKU1')->value('id'), ProductMarketplace::where('marketplace_sku', 'AMZ-1')->value('product_id'));
    }
}
