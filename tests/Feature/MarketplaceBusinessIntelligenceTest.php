<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Services\MarketplaceBusinessIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceBusinessIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_empty_read_only_metrics(): void
    {
        $marketplace = Marketplace::create(['code' => 'BI_TEST', 'name' => 'BI Test', 'is_active' => true]);

        $report = app(MarketplaceBusinessIntelligenceService::class)->dashboard([
            'from' => '2026-11-01', 'to' => '2026-11-30', 'marketplace_id' => $marketplace->id,
        ]);

        $this->assertSame(0, (int) ($report['totals']->orders ?? 0));
        $this->assertSame(0.0, $report['profit_margin']);
        $this->assertSame(0.0, $report['returns']['rate']);
        $this->assertCount(0, $report['top_products']);
        $this->assertDatabaseCount('order_profits', 0);
        $this->assertDatabaseCount('returns', 0);
    }

    public function test_dashboard_rejects_reversed_dates(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceBusinessIntelligenceService::class)->dashboard(['from' => '2026-11-30', 'to' => '2026-11-01']);
    }

    public function test_dashboard_rejects_out_of_range_margin_threshold(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceBusinessIntelligenceService::class)->dashboard(['low_margin_threshold' => 150]);
    }
}
