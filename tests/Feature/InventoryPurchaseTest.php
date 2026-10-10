<?php
namespace Tests\Feature;

use App\Models\{Inventory, InventoryTransaction, Product, Supplier, User};
use App\Services\{InventoryService, PurchaseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the whole database. Refuse to run on anything but in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: set DB_CONNECTION=sqlite and DB_DATABASE=:memory: in phpunit.xml (never run against your real MySQL).');
        }
        parent::setUp();
    }

    private function product(string $sku = 'SKU1'): Product { return Product::create(['sku' => $sku, 'name' => "Product $sku"]); }
    private function user(): User { return User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']); }
    private function purchase(array $items): \App\Models\Purchase
    {
        $s = Supplier::create(['name' => 'Sup']);
        return app(PurchaseService::class)->save(null, ['supplier_id' => $s->id, 'purchase_date' => '2026-10-08', 'status' => 'ORDERED'], $items, $this->user());
    }

    public function test_receiving_purchase_adds_stock_once(): void
    {
        $p = $this->product();
        $po = $this->purchase([['product_id' => $p->id, 'quantity' => 10, 'unit_cost' => '100', 'gst_rate' => 18]]);
        $this->assertSame('1180.00', (string) $po->total_amount);

        app(PurchaseService::class)->receive($po);
        $this->assertSame(10, Inventory::where('product_id', $p->id)->value('available_qty'));
        $this->assertCount(0, app(InventoryService::class)->mismatches());

        $this->expectException(ValidationException::class);   // second receive must fail
        app(PurchaseService::class)->receive($po->fresh());
    }

    public function test_cannot_sell_more_than_available(): void
    {
        $p = $this->product();
        try {
            app(InventoryService::class)->record($p, 'ORDER', 5);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {}
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_damage_and_disposal_move_buckets(): void
    {
        $p = $this->product(); $svc = app(InventoryService::class);
        $svc->record($p, 'STOCK_IN', 10);
        $svc->record($p, 'DAMAGE', 3);
        $svc->record($p, 'DISPOSAL', 2);
        $inv = Inventory::where('product_id', $p->id)->first();
        $this->assertSame([7, 1], [$inv->available_qty, $inv->damaged_qty]);
        $this->assertCount(0, $svc->mismatches());
    }

    public function test_money_is_exact(): void
    {
        $p = $this->product();
        $po = $this->purchase([['product_id' => $p->id, 'quantity' => 3, 'unit_cost' => '33.33', 'gst_rate' => 18]]);
        $this->assertSame('99.99', (string) $po->subtotal);
        $this->assertSame('18.00', (string) $po->gst_amount);
        $this->assertSame('117.99', (string) $po->total_amount);
    }
}
