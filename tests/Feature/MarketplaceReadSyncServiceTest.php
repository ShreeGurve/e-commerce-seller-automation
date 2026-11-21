<?php

namespace Tests\Feature;

use App\Marketplaces\Sync\MarketplaceReadPage;
use App\Marketplaces\Sync\MarketplaceReadProvider;
use App\Models\Business;
use App\Models\Inventory;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventorySnapshot;
use App\Models\MarketplaceSyncRecord;
use App\Models\Order;
use App\Services\MarketplaceReadSyncService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceReadSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
    }

    public function test_read_sync_stages_orders_without_customer_pii_and_is_idempotent(): void
    {
        $connection = $this->connection();
        config([
            'marketplace_sync.providers.amazon' => FakeMarketplaceReadProvider::class,
            'marketplaces.amazon.enabled' => true,
            'marketplaces.amazon.api_calls_enabled' => true,
        ]);

        $first = app(MarketplaceReadSyncService::class)->sync($connection, 'orders');
        $second = app(MarketplaceReadSyncService::class)->sync($connection, 'orders');

        $this->assertSame('COMPLETED', $first->status);
        $this->assertSame(1, $first->records_created);
        $this->assertSame('COMPLETED', $second->status);
        $this->assertSame(1, $second->records_updated);
        $this->assertSame(1, MarketplaceSyncRecord::where('resource', 'orders')->count());

        $payload = MarketplaceSyncRecord::where('resource', 'orders')->firstOrFail()->payload;
        $this->assertArrayNotHasKey('customer_email', $payload);
        $this->assertArrayNotHasKey('shipping_address', $payload);
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame(0, Order::count(), 'Read sync must not create operational orders.');
    }

    public function test_inventory_sync_creates_marketplace_snapshot_without_changing_internal_inventory(): void
    {
        $connection = $this->connection();
        config([
            'marketplace_sync.providers.amazon' => FakeMarketplaceReadProvider::class,
            'marketplaces.amazon.enabled' => true,
            'marketplaces.amazon.api_calls_enabled' => true,
        ]);

        $run = app(MarketplaceReadSyncService::class)->sync($connection, 'inventory');

        $this->assertSame('COMPLETED', $run->status);
        $snapshot = MarketplaceInventorySnapshot::firstOrFail();
        $this->assertSame('SKU-REMOTE-1', $snapshot->marketplace_sku);
        $this->assertSame(12, $snapshot->available_qty);
        $this->assertSame(2, $snapshot->reserved_qty);
        $this->assertSame(0, Inventory::count());
    }

    public function test_unverified_connection_and_unknown_resource_are_rejected(): void
    {
        $connection = $this->connection(['status' => 'pending']);
        config([
            'marketplace_sync.providers.amazon' => FakeMarketplaceReadProvider::class,
            'marketplaces.amazon.enabled' => true,
            'marketplaces.amazon.api_calls_enabled' => true,
        ]);

        try {
            app(MarketplaceReadSyncService::class)->sync($connection, 'orders');
            $this->fail('Pending connections must not synchronize.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('verified', $exception->getMessage());
        }

        $connection->update(['status' => 'connected']);
        $this->expectException(\RuntimeException::class);
        app(MarketplaceReadSyncService::class)->sync($connection, 'prices');
    }

    private function connection(array $overrides = []): MarketplaceConnection
    {
        $business = Business::create(['name' => 'Sync Test Business']);
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);

        return MarketplaceConnection::create(array_merge([
            'business_id' => $business->id,
            'marketplace_id' => $marketplace->id,
            'environment' => 'sandbox',
            'account_label' => 'Test Seller',
            'credentials' => ['client_id' => 'test', 'client_secret' => 'not-a-real-secret'],
            'status' => 'connected',
        ], $overrides));
    }
}

class FakeMarketplaceReadProvider implements MarketplaceReadProvider
{
    public function fetchPage(
        MarketplaceConnection $connection,
        string $resource,
        ?DateTimeInterface $from,
        ?DateTimeInterface $to,
        ?string $cursor
    ): MarketplaceReadPage {
        if ($resource === 'inventory') {
            return new MarketplaceReadPage([[
                'external_id' => 'SKU-REMOTE-1:DEFAULT',
                'payload' => [
                    'marketplace_sku' => 'SKU-REMOTE-1',
                    'fulfillment_channel' => 'DEFAULT',
                    'available_qty' => 12,
                    'reserved_qty' => 2,
                ],
            ]]);
        }

        return new MarketplaceReadPage([[
            'external_id' => 'ORDER-REMOTE-1',
            'payload' => [
                'status' => 'Unshipped',
                'total_amount' => 499.00,
                'customer_email' => 'private@example.test',
                'shipping_address' => ['line1' => 'Private Street', 'city' => 'Private City'],
            ],
        ]]);
    }
}
