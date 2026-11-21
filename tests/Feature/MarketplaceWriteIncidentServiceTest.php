<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\Product;
use App\Models\User;
use App\Services\MarketplaceWriteIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceWriteIncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $user;

    private MarketplaceConnection $connection;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Use isolated SQLite in-memory tests.');
        }
        parent::setUp();
        $this->business = Business::create(['name' => 'Incident Test Business']);
        $this->user = User::forceCreate(['name' => 'Incident User', 'email' => 'incident-'.uniqid().'@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $this->connection = MarketplaceConnection::create(['business_id' => $this->business->id, 'marketplace_id' => $marketplace->id,
            'environment' => 'production', 'account_label' => 'Incident Amazon', 'credentials' => [], 'status' => 'connected']);
    }

    public function test_creates_business_scoped_incident_and_is_idempotent_for_same_attempt_and_title(): void
    {
        $attempt = $this->attempt($this->business->id, $this->user);
        $service = app(MarketplaceWriteIncidentService::class);
        $first = $service->openFromAttempt($attempt, $this->user, ['title' => 'Submission uncertain', 'severity' => 'HIGH']);
        $second = $service->openFromAttempt($attempt, $this->user, ['title' => 'Submission uncertain', 'severity' => 'CRITICAL']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('marketplace_write_incident_events')->where('incident_id', $first->id)->count());
    }

    public function test_rejects_attempt_from_another_business(): void
    {
        $other = Business::create(['name' => 'Other Incident Business']);
        $attempt = $this->attempt($other->id, User::forceCreate(['name' => 'Other', 'email' => 'other-'.uniqid().'@test.local', 'password' => 'x', 'business_id' => $other->id]));
        $this->expectException(ValidationException::class);
        app(MarketplaceWriteIncidentService::class)->openFromAttempt($attempt, $this->user, ['title' => 'Cross tenant', 'severity' => 'HIGH']);
    }

    public function test_closed_incident_cannot_be_reopened_and_history_is_append_only(): void
    {
        $attempt = $this->attempt($this->business->id, $this->user);
        $service = app(MarketplaceWriteIncidentService::class);
        $incident = $service->openFromAttempt($attempt, $this->user, ['title' => 'Failed update', 'severity' => 'MEDIUM']);
        $service->update($incident->id, $this->user, ['status' => 'RESOLVED', 'note' => 'Checked in seller portal']);
        $before = DB::table('marketplace_write_incident_events')->where('incident_id', $incident->id)->count();
        try {
            $service->update($incident->id, $this->user, ['status' => 'OPEN']);
            $this->fail('Expected closed incident transition to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertSame($before, DB::table('marketplace_write_incident_events')->where('incident_id', $incident->id)->count());
    }

    private function attempt(int $businessId, User $owner): int
    {
        $product = Product::create(['sku' => 'INC-'.uniqid(), 'name' => 'Incident Product', 'created_by' => $owner->id]);
        $ruleId = DB::table('marketplace_automation_rules')->value('id');
        $taskId = DB::table('marketplace_automation_tasks')->insertGetId([
            'rule_id' => $ruleId, 'product_id' => $product->id, 'recommendation_type' => 'LOW_PROFIT_MARGIN', 'priority' => 'HIGH',
            'title' => 'Incident test task', 'reason' => 'Test reason', 'suggested_action' => 'Review', 'metrics' => json_encode([]),
            'recommendation_snapshot' => json_encode([]), 'dedupe_key' => hash('sha256', uniqid('', true)), 'status' => 'APPROVED',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('marketplace_write_attempts')->insertGetId([
            'business_id' => $businessId, 'marketplace_connection_id' => $this->connection->id, 'task_id' => $taskId,
            'marketplace_code' => 'amazon', 'operation' => 'PRICE_UPDATE', 'status' => 'FAILED', 'marketplace_sku' => 'SKU-'.uniqid(),
            'idempotency_key' => uniqid('incident-', true), 'payload_hash' => hash('sha256', uniqid('', true)),
            'request_payload' => json_encode(['test' => true]), 'prepared_by' => $owner->id, 'approved_by' => $owner->id,
            'created_at' => now(), 'updated_at' => now(), 'submitted_at' => now(),
        ]);
    }
}
