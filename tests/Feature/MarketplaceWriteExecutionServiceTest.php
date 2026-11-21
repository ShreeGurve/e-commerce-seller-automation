<?php

namespace Tests\Feature;

use App\Marketplaces\Writes\AmazonListingsWriteAdapter;
use App\Marketplaces\Writes\FlipkartListingsWriteAdapter;
use App\Models\Business;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\Product;
use App\Models\ProductMarketplace;
use App\Models\User;
use App\Services\MarketplaceWriteExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MarketplaceWriteExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $user;

    private User $secondUser;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite and DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
        $this->business = Business::create(['name' => 'Write Test Business']);
        $this->user = User::forceCreate(['name' => 'Preparer', 'email' => 'write-preparer@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
        $this->secondUser = User::forceCreate(['name' => 'Approver', 'email' => 'write-approver@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
    }

    public function test_amazon_price_preview_is_local_only_and_uses_mapped_listing_sku(): void
    {
        [$connection, $taskId] = $this->fixture('amazon', 'LOW_PROFIT_MARGIN', ['product_type' => 'PRODUCT']);
        $attempt = app(MarketplaceWriteExecutionService::class)->prepare($this->user, [
            'connection_id' => $connection->id, 'task_id' => $taskId, 'operation' => 'PRICE_UPDATE', 'selling_price' => '149.50',
            'idempotency_key' => 'amazon-price-preview-001',
        ]);

        $payload = json_decode($attempt->request_payload, true);
        $this->assertSame('PREPARED', $attempt->status);
        $this->assertSame('AMZ-EXT-001', $attempt->marketplace_sku);
        $this->assertSame('/attributes/purchasable_offer', $payload['patches'][0]['path']);
        $this->assertSame(149.5, $payload['patches'][0]['value'][0]['our_price'][0]['schedule'][0]['value_with_tax']);
        $this->assertDatabaseCount('marketplace_write_attempts', 1);
    }

    public function test_flipkart_inventory_preview_requires_location_and_builds_v3_payload(): void
    {
        [$connection, $taskId] = $this->fixture('flipkart', 'RESTOCK', ['external_product_id' => '1234567890123']);
        $attempt = app(MarketplaceWriteExecutionService::class)->prepare($this->user, [
            'connection_id' => $connection->id, 'task_id' => $taskId, 'operation' => 'INVENTORY_UPDATE',
            'quantity' => 8, 'location_id' => 'LOC-001', 'idempotency_key' => 'flipkart-inventory-preview-1',
        ]);
        $payload = json_decode($attempt->request_payload, true);
        $this->assertSame('PREPARED', $attempt->status);
        $this->assertSame('LOC-001', $payload['marketplace_payload']['locations'][0]['id']);
        $this->assertSame(8, $payload['marketplace_payload']['locations'][0]['inventory']);
        $this->assertSame('1234567890123', $payload['marketplace_payload']['product_id']);
    }

    public function test_same_user_cannot_approve_their_own_write_preview(): void
    {
        [$connection, $taskId] = $this->fixture('amazon', 'LOW_PROFIT_MARGIN', ['product_type' => 'PRODUCT']);
        $attempt = app(MarketplaceWriteExecutionService::class)->prepare($this->user, [
            'connection_id' => $connection->id, 'task_id' => $taskId, 'operation' => 'PRICE_UPDATE', 'selling_price' => 100,
        ]);

        $this->expectException(ValidationException::class);
        app(MarketplaceWriteExecutionService::class)->approveAndExecute($attempt->id, $this->user);
    }

    public function test_live_submission_is_blocked_when_global_flags_are_off(): void
    {
        [$connection, $taskId] = $this->fixture('amazon', 'LOW_PROFIT_MARGIN', ['product_type' => 'PRODUCT']);
        $attempt = app(MarketplaceWriteExecutionService::class)->prepare($this->user, [
            'connection_id' => $connection->id, 'task_id' => $taskId, 'operation' => 'PRICE_UPDATE', 'selling_price' => 100,
        ]);
        DB::table('marketplace_write_policies')->insert([
            'marketplace_connection_id' => $connection->id, 'operation' => 'PRICE_UPDATE', 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        config([
            'marketplace_writes.enabled' => false,
            'marketplaces.push_enabled' => false,
            'marketplace_writes.amazon_enabled' => false,
            'marketplaces.amazon.enabled' => false,
            'marketplaces.amazon.api_calls_enabled' => false,
        ]);

        $this->expectException(RuntimeException::class);
        app(MarketplaceWriteExecutionService::class)->approveAndExecute($attempt->id, $this->secondUser);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_payload(): void
    {
        [$connection, $taskId] = $this->fixture('amazon', 'LOW_PROFIT_MARGIN', ['product_type' => 'PRODUCT']);
        $service = app(MarketplaceWriteExecutionService::class);
        $base = ['connection_id' => $connection->id, 'task_id' => $taskId, 'operation' => 'PRICE_UPDATE', 'idempotency_key' => 'same-key-001'];
        $service->prepare($this->user, $base + ['selling_price' => 100]);

        $this->expectException(ValidationException::class);
        $service->prepare($this->user, $base + ['selling_price' => 120]);
    }

    public function test_amazon_adapter_requires_accepted_submission_status_not_only_http_200(): void
    {
        [$connection] = $this->fixture('amazon', 'LOW_PROFIT_MARGIN', ['product_type' => 'PRODUCT']);
        config(['marketplaces.amazon.enabled' => true, 'marketplaces.amazon.api_calls_enabled' => true]);
        Http::fakeSequence()
            ->push(['access_token' => 'test-access-token', 'expires_in' => 3600], 200)
            ->push(['sku' => 'AMAZON-EXT-001', 'status' => 'INVALID', 'submissionId' => 'submission-1', 'issues' => [['severity' => 'ERROR']]], 200);

        $result = app(AmazonListingsWriteAdapter::class)->submit($connection, [
            'sku' => 'AMAZON-EXT-001', 'product_type' => 'PRODUCT',
            'patches' => [['op' => 'replace', 'path' => '/attributes/purchasable_offer', 'value' => []]],
        ]);

        $this->assertFalse($result['accepted']);
        $this->assertSame(200, $result['http_status']);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), '/listings/2021-08-01/items/SELLER123/AMAZON-EXT-001?marketplaceIds=A21TJRUUN4KGV'));
    }

    public function test_flipkart_adapter_checks_per_sku_success_on_http_200(): void
    {
        [$connection] = $this->fixture('flipkart', 'RESTOCK');
        config(['marketplaces.flipkart.enabled' => true, 'marketplaces.flipkart.api_calls_enabled' => true]);
        Http::fakeSequence()
            ->push(['access_token' => 'flipkart-test-token', 'expires_in' => 3600], 200)
            ->push(['FLIPKART-EXT-001' => ['status' => 'success', 'errors' => []]], 200);

        $result = app(FlipkartListingsWriteAdapter::class)->submit($connection, [
            'operation' => 'INVENTORY_UPDATE', 'sku' => 'FLIPKART-EXT-001',
            'marketplace_payload' => ['product_id' => '1234567890123', 'locations' => [['id' => 'LOC-1', 'inventory' => 4]]],
        ]);

        $this->assertTrue($result['accepted']);
        $this->assertSame('success', $result['sku_status']);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/sellers/listings/v3/update/inventory'));
    }

    public function test_write_preview_rejects_unapproved_task(): void
    {
        [$connection, $taskId] = $this->fixture('amazon', 'LOW_PROFIT_MARGIN', ['product_type' => 'PRODUCT'], 'PENDING');
        $this->expectException(ValidationException::class);
        app(MarketplaceWriteExecutionService::class)->prepare($this->user, [
            'connection_id' => $connection->id, 'task_id' => $taskId, 'operation' => 'PRICE_UPDATE', 'selling_price' => 100,
        ]);
    }

    private function fixture(string $code, string $taskType, array $mappingOverrides = [], string $taskStatus = 'APPROVED'): array
    {
        $marketplace = Marketplace::create(['code' => $code, 'name' => ucfirst($code)]);
        $credentials = $code === 'amazon'
            ? ['client_id' => 'client', 'client_secret' => 'secret', 'refresh_token' => 'refresh', 'marketplace_id' => 'A21TJRUUN4KGV', 'seller_id' => 'SELLER123', 'region' => 'eu']
            : ['app_id' => 'app', 'app_secret' => 'secret'];
        $connection = MarketplaceConnection::create([
            'business_id' => $this->business->id, 'marketplace_id' => $marketplace->id, 'environment' => 'production',
            'account_label' => 'Test '.$code, 'credentials' => $credentials, 'status' => 'connected',
        ]);
        $product = Product::create(['sku' => 'INT-001', 'name' => 'Write Test Product', 'product_type' => $mappingOverrides['product_type'] ?? null, 'created_by' => $this->user->id]);
        ProductMarketplace::create(array_merge([
            'product_id' => $product->id, 'marketplace_id' => $marketplace->id, 'marketplace_sku' => ($code === 'amazon' ? 'AMZ' : strtoupper($code)).'-EXT-001',
            'external_product_id' => $code === 'flipkart' ? '1234567890123' : null,
        ], array_intersect_key($mappingOverrides, array_flip(['external_product_id']))));
        $ruleId = DB::table('marketplace_automation_rules')->where('recommendation_type', $taskType)->value('id');
        $taskId = DB::table('marketplace_automation_tasks')->insertGetId([
            'rule_id' => $ruleId, 'product_id' => $product->id, 'recommendation_type' => $taskType, 'priority' => 'HIGH',
            'title' => 'Approved marketplace write task', 'reason' => 'Test reason', 'suggested_action' => 'Review and update listing',
            'metrics' => json_encode([]), 'recommendation_snapshot' => json_encode([]), 'dedupe_key' => hash('sha256', uniqid('', true)),
            'status' => $taskStatus, 'reviewed_by' => $this->secondUser->id, 'reviewed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$connection, $taskId];
    }
}
