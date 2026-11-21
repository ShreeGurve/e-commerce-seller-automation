<?php

namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\Marketplace;
use App\Models\Settlement;
use App\Models\User;
use App\Services\MarketplacePayoutReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplacePayoutReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Marketplace $marketplace;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Use DB_CONNECTION=sqlite and DB_DATABASE=:memory:; never run RefreshDatabase against production MySQL.');
        }
        parent::setUp();
        $this->user = User::forceCreate(['name' => 'Payout Tester', 'email' => 'payout@test.local', 'password' => 'x']);
        $this->marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon', 'adapter_class' => AmazonAdapter::class]);
    }

    private function settlement(float $amount = 1000): Settlement
    {
        return Settlement::create([
            'marketplace_id' => $this->marketplace->id,
            'external_settlement_id' => 'SET-'.uniqid(),
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
            'total_amount' => $amount,
            'item_count' => 4,
        ]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'marketplace_id' => $this->marketplace->id,
            'bank_reference' => 'UTR-'.uniqid(),
            'bank_amount' => 1000,
            'currency' => 'INR',
            'payout_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_recording_bank_credit_within_one_rupee_is_matched(): void
    {
        $settlement = $this->settlement(1000);
        $payout = app(MarketplacePayoutReconciliationService::class)->record(
            $this->data(['settlement_id' => $settlement->id, 'bank_amount' => 1000.50]), $this->user->id
        );
        $this->assertSame('MATCHED', $payout->status);
        $this->assertSame('0.50', $payout->difference);
        $this->assertSame($settlement->id, $payout->settlement_id);
    }

    public function test_recording_amount_difference_over_one_rupee_is_flagged(): void
    {
        $settlement = $this->settlement(1000);
        $payout = app(MarketplacePayoutReconciliationService::class)->record(
            $this->data(['settlement_id' => $settlement->id, 'bank_amount' => 980]), $this->user->id
        );
        $this->assertSame('MISMATCH', $payout->status);
        $this->assertSame('-20.00', $payout->difference);
    }

    public function test_unmatched_payout_can_be_linked_later_and_recalculated(): void
    {
        $service = app(MarketplacePayoutReconciliationService::class);
        $payout = $service->record($this->data(['bank_amount' => 1000]), $this->user->id);
        $this->assertSame('UNMATCHED', $payout->status);
        $linked = $service->link($payout, $this->settlement(1000), $this->user->id);
        $this->assertSame('MATCHED', $linked->status);
        $this->assertNotNull($linked->reviewed_at);
    }

    public function test_payout_cannot_be_linked_to_another_marketplace_or_duplicate_settlement(): void
    {
        $service = app(MarketplacePayoutReconciliationService::class);
        $payout = $service->record($this->data(), $this->user->id);
        $other = Marketplace::create(['code' => 'flipkart', 'name' => 'Flipkart', 'adapter_class' => AmazonAdapter::class]);
        $foreignSettlement = Settlement::create(['marketplace_id' => $other->id, 'external_settlement_id' => 'FK-1', 'total_amount' => 1000, 'item_count' => 1]);
        try {
            $service->link($payout, $foreignSettlement, $this->user->id);
            $this->fail('Expected marketplace validation exception.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settlement_id', $e->errors());
        }

        $settlement = $this->settlement();
        $first = $service->link($payout, $settlement, $this->user->id);
        $this->assertSame('MATCHED', $first->status);
        $second = $service->record($this->data(['bank_amount' => 1000]), $this->user->id);
        $this->expectException(ValidationException::class);
        $service->link($second, $settlement, $this->user->id);
    }

    public function test_duplicate_bank_reference_is_rejected_for_same_marketplace(): void
    {
        $service = app(MarketplacePayoutReconciliationService::class);
        $data = $this->data(['bank_reference' => 'UTR-DUP-001']);
        $service->record($data, $this->user->id);
        $this->expectException(ValidationException::class);
        $service->record($data, $this->user->id);
    }

    public function test_ignored_payout_can_be_reopened_and_reconciled(): void
    {
        $service = app(MarketplacePayoutReconciliationService::class);
        $payout = $service->record($this->data(), $this->user->id);
        $service->ignore($payout, 'Duplicate bank feed row', $this->user->id);
        $this->assertSame('IGNORED', $payout->fresh()->status);
        $service->reopen($payout, $this->user->id);
        $this->assertSame('UNMATCHED', $payout->fresh()->status);
    }
}
