<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\Product;
use App\Models\User;
use App\Services\MarketplaceWriteAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceWriteAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private User $user;
    private MarketplaceConnection $connection;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite and DB_DATABASE=:memory:.');
        }
        parent::setUp();
        $this->business = Business::create(['name' => 'Write Audit Business']);
        $this->user = User::forceCreate(['name' => 'Audit User', 'email' => 'audit-user@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $this->connection = MarketplaceConnection::create(['business_id' => $this->business->id, 'marketplace_id' => $marketplace->id,
            'environment' => 'production', 'account_label' => 'Audit Amazon', 'credentials' => [], 'status' => 'connected']);
    }

    public function test_dashboard_is_business_scoped_and_returns_lifecycle_counts(): void
    {
        $this->attempt($this->business->id, 'ACCEPTED');
        $other = Business::create(['name' => 'Other Business']);
        $this->attempt($other->id, 'FAILED');

        $data = app(MarketplaceWriteAuditService::class)->dashboard($this->business->id);
        $this->assertCount(1, $data['attempts']);
        $this->assertSame(1, $data['counts']['total']);
        $this->assertSame(1, $data['counts']['accepted']);
        $this->assertSame(0, $data['counts']['failed']);
    }

    public function test_filters_limit_attempts_by_marketplace_operation_and_status(): void
    {
        $this->attempt($this->business->id, 'ACCEPTED', 'amazon', 'PRICE_UPDATE');
        $this->attempt($this->business->id, 'FAILED', 'amazon', 'INVENTORY_UPDATE');
        $filters = app(MarketplaceWriteAuditService::class)->normalizeFilters(['status' => 'failed', 'operation' => 'inventory_update', 'marketplace' => 'AMAZON']);
        $data = app(MarketplaceWriteAuditService::class)->dashboard($this->business->id, $filters);
        $this->assertCount(1, $data['attempts']);
        $this->assertSame('FAILED', $data['attempts']->first()->status);
    }

    public function test_rejects_invalid_filter_values_and_reversed_dates(): void
    {
        $service = app(MarketplaceWriteAuditService::class);
        try {
            $service->normalizeFilters(['status' => 'UNKNOWN']);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->expectException(ValidationException::class);
        $service->normalizeFilters(['from' => '2026-11-20', 'to' => '2026-11-01']);
    }

    private function attempt(int $businessId, string $status, string $marketplace = 'amazon', string $operation = 'PRICE_UPDATE'): void
    {
        $owner = $businessId === (int) $this->business->id ? $this->user : User::forceCreate([
            'name' => 'Other Audit User', 'email' => 'other-audit-'.uniqid().'@test.local', 'password' => 'x', 'business_id' => $businessId,
        ]);
        $product = Product::create(['sku' => 'AUD-'.uniqid(), 'name' => 'Audit Product', 'created_by' => $owner->id]);
        $ruleId = DB::table('marketplace_automation_rules')->value('id');
        $taskId = DB::table('marketplace_automation_tasks')->insertGetId([
            'rule_id' => $ruleId, 'product_id' => $product->id, 'recommendation_type' => 'LOW_PROFIT_MARGIN', 'priority' => 'HIGH',
            'title' => 'Audit test task', 'reason' => 'Test reason', 'suggested_action' => 'Review', 'metrics' => json_encode([]),
            'recommendation_snapshot' => json_encode([]), 'dedupe_key' => hash('sha256', uniqid('', true)), 'status' => 'APPROVED',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_write_attempts')->insert([
            'business_id' => $businessId, 'marketplace_connection_id' => $this->connection->id,
            'task_id' => $taskId,
            'marketplace_code' => $marketplace, 'operation' => $operation, 'status' => $status,
            'marketplace_sku' => 'SKU-'.uniqid(), 'idempotency_key' => uniqid('audit-', true),
            'payload_hash' => hash('sha256', uniqid('', true)), 'request_payload' => json_encode(['test' => true]),
            'prepared_by' => $owner->id, 'approved_by' => $owner->id,
            'created_at' => now(), 'updated_at' => now(), 'submitted_at' => $status === 'PREPARED' ? null : now(),
        ]);
    }
}
