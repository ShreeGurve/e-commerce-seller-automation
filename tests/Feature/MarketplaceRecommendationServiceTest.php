<?php

namespace Tests\Feature;

use App\Models\Marketplace;
use App\Models\Product;
use App\Services\MarketplaceRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_data_returns_empty_read_only_recommendations(): void
    {
        $report = app(MarketplaceRecommendationService::class)->dashboard(['days' => 30]);
        $this->assertSame(0, $report['counts']['total']);
        $this->assertCount(0, $report['recommendations']);
        $this->assertDatabaseCount('inventory', 0);
        $this->assertDatabaseCount('pricing_recommendations', 0);
    }

    public function test_low_stock_recommendation_is_generated_without_changing_inventory(): void
    {
        $marketplace = Marketplace::create(['code' => 'REC_TEST', 'name' => 'Recommendation Test', 'is_active' => true]);
        $product = Product::create(['sku' => 'REC-LOW-1', 'name' => 'Low Stock Product', 'status' => 'active']);
        DB::table('inventory')->insert([
            'product_id' => $product->id, 'available_qty' => 3, 'reserved_qty' => 0,
            'damaged_qty' => 0, 'returned_qty' => 0, 'in_transit_qty' => 0,
            'minimum_stock' => 5, 'reorder_level' => 8, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $report = app(MarketplaceRecommendationService::class)->dashboard();
        $this->assertContains('RESTOCK', array_column($report['recommendations'], 'type'));
        $this->assertDatabaseHas('inventory', ['product_id' => $product->id, 'available_qty' => 3]);
    }

    public function test_invalid_analysis_period_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceRecommendationService::class)->dashboard(['days' => 45]);
    }

    public function test_invalid_stock_cover_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(MarketplaceRecommendationService::class)->dashboard(['low_stock_cover' => 90]);
    }
}
