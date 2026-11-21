<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Services\MarketplaceFinancialAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceFinancialAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_empty_period_totals_without_mutating_financial_records(): void
    {
        $marketplace = Marketplace::create(['code' => 'ANALYTICS_TEST', 'name' => 'Analytics Test', 'is_active' => true]);

        $report = app(MarketplaceFinancialAnalyticsService::class)->dashboard([
            'from' => '2026-11-01', 'to' => '2026-11-30', 'marketplace_id' => $marketplace->id,
        ]);

        $this->assertSame(0, (int) ($report['totals']->orders ?? 0));
        $this->assertSame(0, (int) ($report['settlements']->count ?? 0));
        $this->assertSame(0, (int) ($report['payouts']->count ?? 0));
        $this->assertSame(0.0, $report['estimated_operating_profit']);
        $this->assertDatabaseCount('settlements', 0);
        $this->assertDatabaseCount('order_profits', 0);
    }

    public function test_dashboard_rejects_a_reversed_date_range(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceFinancialAnalyticsService::class)->dashboard(['from' => '2026-11-30', 'to' => '2026-11-01']);
    }
}
