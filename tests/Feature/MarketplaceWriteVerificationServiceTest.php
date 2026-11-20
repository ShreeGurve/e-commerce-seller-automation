<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Models\Product;
use App\Models\ProductMarketplace;
use App\Models\User;
use App\Services\MarketplaceWriteVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MarketplaceWriteVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private User $preparer;
    private User $submitter;
    private User $verifier;
    private int $attemptId;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite and DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();

        $this->business = Business::create(['name' => 'Write Verification Test Business']);
        $this->preparer = User::forceCreate(['name' => 'Preview User', 'email' => 'verify-preview@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
        $this->submitter = User::forceCreate(['name' => 'Submit User', 'email' => 'verify-submit@test.local', 'password' => 'x', 'business_id' => $this->business->id]);
        $this->verifier = User::forceCreate(['name' => 'Verify User', 'email' => 'verify-check@test.local', 'password' => 'x', 'business_id' => $this->business->id]);

        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $connection = MarketplaceConnection::create([
            'business_id' => $this->business->id, 'marketplace_id' => $marketplace->id,
            'environment' => 'production', 'account_label' => 'Verification Test',
            'credentials' => ['marketplace_id' => 'A21TJRUUN4KGV'], 'status' => 'connected',
        ]);
        $product = Product::create(['sku' => 'VERIFY-001', 'name' => 'Verification Product', 'created_by' => $this->preparer->id]);
        ProductMarketplace::create(['product_id' => $product->id, 'marketplace_id' => $marketplace->id, 'marketplace_sku' => 'VERIFY-EXT-001']);

        $ruleId = DB::table('marketplace_automation_rules')->where('recommendation_type', 'LOW_PROFIT_MARGIN')->value('id');
        $taskId = DB::table('marketplace_automation_tasks')->insertGetId([
            'rule_id' => $ruleId, 'product_id' => $product->id, 'recommendation_type' => 'LOW_PROFIT_MARGIN',
            'priority' => 'HIGH', 'title' => 'Verification fixture task', 'reason' => 'Fixture',
            'suggested_action' => 'Review listing', 'metrics' => json_encode([]), 'recommendation_snapshot' => json_encode([]),
            'dedupe_key' => hash('sha256', 'verification-fixture'), 'status' => 'APPROVED',
            'reviewed_by' => $this->submitter->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->attemptId = DB::table('marketplace_write_attempts')->insertGetId([
            'business_id' => $this->business->id, 'marketplace_connection_id' => $connection->id, 'task_id' => $taskId,
            'marketplace_code' => 'amazon', 'operation' => 'PRICE_UPDATE', 'status' => 'ACCEPTED',
            'marketplace_sku' => 'VERIFY-EXT-001', 'idempotency_key' => 'verification-fixture-key',
            'payload_hash' => hash('sha256', '{}'), 'request_payload' => '{}',
            'prepared_by' => $this->preparer->id, 'approved_by' => $this->submitter->id,
            'approved_at' => now(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_records_manual_live_confirmation_and_appends_event(): void
    {
        $result = app(MarketplaceWriteVerificationService::class)->record($this->attemptId, $this->verifier, [
            'status' => 'LIVE_CONFIRMED', 'verification_note' => 'Checked Seller Central; new price is visible on the listing.',
            'external_submission_id' => 'AMZ-SUB-123', 'evidence_url' => 'https://sellercentral.amazon.in/example',
        ]);

        $this->assertSame('LIVE_CONFIRMED', $result->status);
        $this->assertDatabaseHas('marketplace_write_verification_events', [
            'attempt_id' => $this->attemptId, 'from_status' => null, 'to_status' => 'LIVE_CONFIRMED',
            'recorded_by' => $this->verifier->id,
        ]);
        $this->assertDatabaseHas('marketplace_write_attempts', ['id' => $this->attemptId, 'status' => 'ACCEPTED']);
    }

    public function test_submitter_cannot_verify_own_marketplace_submission(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceWriteVerificationService::class)->record($this->attemptId, $this->submitter, [
            'status' => 'LIVE_CONFIRMED', 'verification_note' => 'Checked the listing and confirmed it is live.',
        ]);
    }

    public function test_non_https_evidence_link_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceWriteVerificationService::class)->record($this->attemptId, $this->verifier, [
            'status' => 'PENDING', 'verification_note' => 'The marketplace is still processing this submission.',
            'evidence_url' => 'http://example.com/evidence',
        ]);
    }

    public function test_failed_submission_cannot_be_marked_as_verified(): void
    {
        DB::table('marketplace_write_attempts')->where('id', $this->attemptId)->update(['status' => 'FAILED']);
        $this->expectException(ValidationException::class);
        app(MarketplaceWriteVerificationService::class)->record($this->attemptId, $this->verifier, [
            'status' => 'PENDING', 'verification_note' => 'Checking the failed marketplace request.',
        ]);
    }

    public function test_live_confirmation_is_final_and_cannot_be_overwritten(): void
    {
        $service = app(MarketplaceWriteVerificationService::class);
        $service->record($this->attemptId, $this->verifier, [
            'status' => 'LIVE_CONFIRMED', 'verification_note' => 'Seller portal shows the requested price is live.',
        ]);
        $this->expectException(ValidationException::class);
        $service->record($this->attemptId, $this->preparer, [
            'status' => 'REJECTED', 'verification_note' => 'Trying to change the final verification record.',
        ]);
    }

    public function test_verification_history_is_scoped_to_the_business(): void
    {
        $service = app(MarketplaceWriteVerificationService::class);
        $service->record($this->attemptId, $this->verifier, [
            'status' => 'PENDING', 'verification_note' => 'Seller portal still shows this update as processing.',
        ]);
        $this->assertCount(1, $service->history($this->attemptId, $this->preparer));
        $otherBusiness = Business::create(['name' => 'Other Business']);
        $otherUser = User::forceCreate(['name' => 'Other User', 'email' => 'verify-other@test.local', 'password' => 'x', 'business_id' => $otherBusiness->id]);
        try {
            $service->history($this->attemptId, $otherUser);
            $this->fail('A user from another business must not access verification history.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Marketplace write attempt was not found.', $exception->getMessage());
        }
    }
}
