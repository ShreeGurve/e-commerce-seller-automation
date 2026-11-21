<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Models\Product;
use App\Models\ProductMarketplace;
use App\Services\MarketplaceOrderImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MarketplaceOrderImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
    }

    public function test_order_preview_resolves_marketplace_sku_to_internal_product(): void
    {
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $product = Product::create(['sku' => 'INTERNAL-1', 'name' => 'Mapped item', 'gst_rate' => 18]);
        ProductMarketplace::create([
            'product_id' => $product->id,
            'marketplace_id' => $marketplace->id,
            'marketplace_sku' => 'SELLER-SKU-1',
        ]);

        $result = $this->buildPreview([
            'created_at' => '2026-11-18T10:00:00Z',
            'fulfillment_status' => 'UNSHIPPED',
            'fulfilled_by' => 'MERCHANT',
            'items' => [[
                'order_item_id' => 'LINE-1', 'sku' => 'SELLER-SKU-1', 'quantity' => 2,
                'unit_price' => '250.00', 'currency' => 'INR',
            ]],
        ], $marketplace->id);

        $this->assertSame([], $result['errors']);
        $this->assertSame($product->id, $result['payload']['items'][0]['product_id']);
        $this->assertSame('SELF', $result['payload']['fulfillment_type']);
    }

    public function test_preview_fails_closed_for_unmapped_sku_and_non_inr_currency(): void
    {
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $result = $this->buildPreview([
            'created_at' => '2026-11-18T10:00:00Z',
            'items' => [[
                'sku' => 'UNKNOWN-SKU', 'quantity' => 1, 'unit_price' => '10.00', 'currency' => 'INR',
            ], [
                'sku' => 'OTHER-SKU', 'quantity' => 1, 'unit_price' => '10.00', 'currency' => 'USD',
            ]],
        ], $marketplace->id);

        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('No product mapping', $result['errors'][0]);
        $this->assertStringContainsString('only INR', $result['errors'][1]);
        $this->assertSame([], $result['payload']['items']);
    }

    private function buildPreview(array $payload, int $marketplaceId): array
    {
        $method = new ReflectionMethod(MarketplaceOrderImportService::class, 'buildPreview');
        $method->setAccessible(true);

        return $method->invoke(app(MarketplaceOrderImportService::class), $payload, $marketplaceId);
    }
}
