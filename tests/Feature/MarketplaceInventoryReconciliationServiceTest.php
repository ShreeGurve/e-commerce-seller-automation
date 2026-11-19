<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Inventory;
use App\Models\InventoryCorrectionRequest;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventoryReconciliationItem;
use App\Models\MarketplaceInventorySnapshot;
use App\Models\Product;
use App\Models\ProductMarketplace;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\MarketplaceInventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceInventoryReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Marketplace $marketplace;
    private MarketplaceConnection $connection;
    private Product $product;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite and DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();

        $business = Business::create(['name' => 'Test Business']);
        $this->user = User::forceCreate(['name' => 'Test User', 'email' => 'inventory-recon@test.local', 'password' => 'x', 'business_id' => $business->id]);
        $this->marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $this->connection = MarketplaceConnection::create([
            'business_id' => $business->id,
            'marketplace_id' => $this->marketplace->id,
            'environment' => 'production',
            'account_label' => 'Test Seller',
            'credentials' => [],
            'status' => 'connected',
        ]);
        $this->product = Product::create(['sku' => 'INT-001', 'name' => 'Test Product']);
        ProductMarketplace::create([
            'product_id' => $this->product->id,
            'marketplace_id' => $this->marketplace->id,
            'marketplace_sku' => 'EXT-001',
            'listing_status' => 'active',
        ]);
        app(InventoryService::class)->record($this->product, 'STOCK_IN', 10);
    }

    private function snapshot(string $channel, int $qty, string $sku = 'EXT-001'): MarketplaceInventorySnapshot
    {
        return MarketplaceInventorySnapshot::create([
            'business_id' => $this->connection->business_id,
            'marketplace_connection_id' => $this->connection->id,
            'marketplace_id' => $this->marketplace->id,
            'marketplace_sku' => $sku,
            'fulfillment_channel' => $channel,
            'available_qty' => $qty,
            'reserved_qty' => 0,
            'inbound_qty' => 0,
            'snapshot_at' => now(),
            'raw_payload' => [],
        ]);
    }

    public function test_reconciliation_detects_mismatch_and_only_creates_pending_correction_request(): void
    {
        $this->snapshot('MFN', 7);

        $run = app(MarketplaceInventoryReconciliationService::class)->reconcile($this->connection);
        $item = MarketplaceInventoryReconciliationItem::where('reconciliation_run_id', $run->id)->firstOrFail();

        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame(1, $run->mismatch_count);
        $this->assertSame('MISMATCH', $item->status);
        $this->assertTrue($item->correction_eligible);
        $this->assertSame(10, (int) $item->internal_qty);
        $this->assertSame(-3, (int) $item->variance);

        $request = app(MarketplaceInventoryReconciliationService::class)->requestCorrection($item, $this->user);

        $this->assertSame('pending', $request->status);
        $this->assertSame(10, (int) $request->system_qty);
        $this->assertSame(7, (int) $request->counted_qty);
        $this->assertSame(-3, (int) $request->variance);
        $this->assertSame('CORRECTION_REQUESTED', $item->fresh()->status);
        $this->assertSame(10, (int) Inventory::where('product_id', $this->product->id)->value('available_qty'), 'Stock must not change before approval.');
        $this->assertSame(1, InventoryCorrectionRequest::count());

        $this->expectException(ValidationException::class);
        app(MarketplaceInventoryReconciliationService::class)->requestCorrection($item->fresh(), $this->user);
    }

    public function test_marketplace_managed_inventory_is_reported_but_cannot_create_internal_correction(): void
    {
        $this->snapshot('FBA', 4);
        $run = app(MarketplaceInventoryReconciliationService::class)->reconcile($this->connection);
        $item = MarketplaceInventoryReconciliationItem::where('reconciliation_run_id', $run->id)->firstOrFail();

        $this->assertSame('MANAGED_CHANNEL', $item->status);
        $this->assertFalse($item->correction_eligible);
        $this->expectException(ValidationException::class);
        app(MarketplaceInventoryReconciliationService::class)->requestCorrection($item, $this->user);
    }

    public function test_unmapped_sku_is_reported_without_guessing_a_product(): void
    {
        $this->snapshot('MFN', 5, 'NO-MAPPING');
        $run = app(MarketplaceInventoryReconciliationService::class)->reconcile($this->connection);
        $item = MarketplaceInventoryReconciliationItem::where('reconciliation_run_id', $run->id)->firstOrFail();

        $this->assertSame('UNMAPPED', $item->status);
        $this->assertNull($item->product_id);
        $this->assertNull($item->variance);
        $this->assertFalse($item->correction_eligible);
    }
}
