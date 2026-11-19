<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\DynamicPricingRule;
use App\Models\DynamicPricingRun;
use App\Models\Marketplace;
use App\Models\Product;
use App\Services\DynamicPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite and DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
    }

    public function test_run_ignores_disabled_rules_and_creates_run_record(): void
    {
        $business = Business::create(['name' => 'Pricing Test']);
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $product = Product::create(['sku' => 'DPR-001', 'name' => 'Rule Product']);
        DynamicPricingRule::create([
            'business_id' => $business->id, 'product_id' => $product->id, 'marketplace_id' => $marketplace->id,
            'strategy' => 'TARGET_MARGIN', 'minimum_margin_percent' => 5, 'competitor_adjustment_percent' => 1, 'enabled' => false,
        ]);

        $run = app(DynamicPricingService::class)->run($business->id);
        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame(0, $run->rules_checked);
        $this->assertSame(0, $run->recommendations_created);
        $this->assertSame(0, $run->skipped_count);
        $this->assertSame(1, DynamicPricingRun::count());
    }

    public function test_rule_fails_closed_when_product_has_no_business_owner(): void
    {
        $business = Business::create(['name' => 'Pricing Test']);
        $marketplace = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon']);
        $product = Product::create(['sku' => 'DPR-002', 'name' => 'No Competitor Product']);
        // No brand is intentionally supplied: service fails closed rather than inferring tenant ownership.
        DynamicPricingRule::create([
            'business_id' => $business->id, 'product_id' => $product->id, 'marketplace_id' => $marketplace->id,
            'strategy' => 'COMPETITOR_MATCH', 'minimum_margin_percent' => 5, 'competitor_adjustment_percent' => 1, 'enabled' => true,
        ]);

        $run = app(DynamicPricingService::class)->run($business->id);
        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame(1, $run->rules_checked);
        $this->assertSame(0, $run->recommendations_created);
        $this->assertSame(1, $run->skipped_count);
    }
}
