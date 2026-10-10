<?php
namespace Tests\Feature;
use App\Models\Listing;
use App\Services\PricingIntelligenceService;
use App\Models\Product;
use Tests\TestCase;
class ListingIntelligenceTest extends TestCase {
 public function test_listing_score_is_bounded_and_has_breakdown(): void {
  $listing=new Listing(['title'=>str_repeat('A',100),'bullet_points'=>[str_repeat('B',150)],'description'=>str_repeat('C',400),'backend_keywords'=>str_repeat('k ',40),'image_urls'=>['a','b','c','d','e'],'has_a_plus_content'=>true]);
  $result=$listing->calculateScore(); $this->assertSame(100,$result['score']); $this->assertArrayHasKey('title',$result['breakdown']);
 }
 public function test_pricing_service_returns_break_even_and_target(): void {
  $product=$this->getMockBuilder(Product::class)->onlyMethods(['currentCost'])->getMock(); $product->method('currentCost')->willReturnCallback(fn($type)=>$type==='COGS'?'100.00':'10.00');
  $result=(new PricingIntelligenceService())->calculate($product,['selling_price'=>200,'other_unit_cost'=>0,'marketplace_fee_percent'=>15,'ad_cost_percent'=>5,'target_margin_percent'=>20]);
  $this->assertEquals(137.5,$result['break_even_price']); $this->assertGreaterThan($result['break_even_price'],$result['target_price']); $this->assertEquals(50,$result['estimated_profit']);
 }
}
