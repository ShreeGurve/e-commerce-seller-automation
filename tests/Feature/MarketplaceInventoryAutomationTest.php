<?php

namespace Tests\Feature;

use App\Jobs\RunMarketplaceInventoryAutomation;
use App\Models\Business;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceInventoryAlert;
use App\Models\MarketplaceInventoryAutomationSetting;
use App\Models\MarketplaceInventoryReconciliationItem;
use App\Models\MarketplaceInventoryReconciliationRun;
use App\Models\MarketplaceInventorySnapshot;
use App\Models\User;
use App\Services\MarketplaceInventoryAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MarketplaceInventoryAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Marketplace $marketplace;

    private MarketplaceConnection $connection;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite and DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
        $this->business = Business::create(['name' => 'Automation Test Business']);
        User::forceCreate(['name' => 'Automation Tester', 'email' => 'automation@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
        $this->marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $this->connection = MarketplaceConnection::create([
            'business_id' => $this->business->id,
            'marketplace_id' => $this->marketplace->id,
            'environment' => 'production',
            'account_label' => 'Automation Seller',
            'credentials' => [],
            'status' => 'connected',
        ]);
    }

    public function test_scheduler_dispatches_only_enabled_due_connections_and_advances_next_run(): void
    {
        Bus::fake();
        $setting = MarketplaceInventoryAutomationSetting::create([
            'business_id' => $this->business->id,
            'marketplace_connection_id' => $this->connection->id,
            'enabled' => true,
            'interval_hours' => 4,
            'low_stock_threshold' => 5,
            'next_run_at' => now()->subMinute(),
        ]);

        $count = app(MarketplaceInventoryAutomationService::class)->scheduleDue();

        $this->assertSame(1, $count);
        Bus::assertDispatched(RunMarketplaceInventoryAutomation::class, fn ($job) => $job->connectionId === $this->connection->id && $job->settingId === $setting->id);
        $this->assertNotNull($setting->fresh()->last_scheduled_at);
        $this->assertTrue($setting->fresh()->next_run_at->greaterThan(now()->addHours(3)));
    }

    public function test_automation_alerts_are_deduplicated_and_flag_low_stock_and_oversell_risk(): void
    {
        $setting = MarketplaceInventoryAutomationSetting::create([
            'business_id' => $this->business->id,
            'marketplace_connection_id' => $this->connection->id,
            'enabled' => true,
            'interval_hours' => 6,
            'low_stock_threshold' => 5,
        ]);
        $run = MarketplaceInventoryReconciliationRun::create([
            'business_id' => $this->business->id,
            'marketplace_connection_id' => $this->connection->id,
            'marketplace_id' => $this->marketplace->id,
            'status' => 'COMPLETED',
            'items_checked' => 1,
            'mismatch_count' => 1,
            'completed_at' => now(),
        ]);
        $snapshot = MarketplaceInventorySnapshot::create([
            'business_id' => $this->business->id,
            'marketplace_connection_id' => $this->connection->id,
            'marketplace_id' => $this->marketplace->id,
            'marketplace_sku' => 'SKU-LOW-1',
            'fulfillment_channel' => 'MFN',
            'available_qty' => 2,
            'reserved_qty' => 0,
            'inbound_qty' => 0,
            'snapshot_at' => now(),
            'raw_payload' => [],
        ]);
        MarketplaceInventoryReconciliationItem::create([
            'reconciliation_run_id' => $run->id,
            'inventory_snapshot_id' => $snapshot->id,
            'marketplace_sku' => 'SKU-LOW-1',
            'fulfillment_channel' => 'MFN',
            'marketplace_qty' => 2,
            'internal_qty' => 9,
            'variance' => -7,
            'status' => 'MISMATCH',
            'correction_eligible' => true,
            'snapshot_at' => now(),
        ]);

        $automation = app(MarketplaceInventoryAutomationService::class);
        $automation->evaluateAlerts($run, $setting);
        $automation->evaluateAlerts($run, $setting);

        $this->assertSame(2, MarketplaceInventoryAlert::count());
        $this->assertDatabaseHas('marketplace_inventory_alerts', [
            'marketplace_connection_id' => $this->connection->id,
            'type' => 'OVERSELL_RISK',
            'severity' => 'critical',
            'marketplace_sku' => 'SKU-LOW-1',
        ]);
        $this->assertDatabaseHas('marketplace_inventory_alerts', [
            'marketplace_connection_id' => $this->connection->id,
            'type' => 'LOW_STOCK',
            'marketplace_sku' => 'SKU-LOW-1',
        ]);
    }
}
