<?php

namespace Tests\Feature;

use App\Models\ListingVersion;
use App\Services\ListingOptimizationService;
use Tests\TestCase;

class ListingOptimizationServiceTest extends TestCase
{
    public function test_it_flags_missing_images_duplicate_bullets_and_unverified_claims(): void
    {
        $version = new ListingVersion([
            'title' => 'Best guaranteed premium water bottle water bottle water bottle',
            'bullets' => ['Leak resistant stainless steel bottle', 'Leak resistant stainless steel bottle'],
            'description' => '100% safe and guaranteed for every user.',
            'backend_keywords' => 'water bottle bottle',
            'image_count' => 0,
        ]);

        $result = app(ListingOptimizationService::class)->analyze($version);
        $codes = array_column($result['issues'], 'code');

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertContains('IMAGE_MISSING', $codes);
        $this->assertContains('BULLETS_DUPLICATE', $codes);
        $this->assertContains('GUARANTEED', $codes);
        $this->assertContains('TITLE_KEYWORD_STUFFING', $codes);
        $this->assertGreaterThan(0, $result['warning_count']);
    }

    public function test_it_returns_good_when_content_has_no_detected_issues(): void
    {
        $version = new ListingVersion([
            'title' => 'Stainless Steel Insulated Travel Bottle with Secure Lid, 750 ml Capacity',
            'bullets' => [
                'Double wall stainless steel construction helps maintain beverage temperature during daily travel and outdoor activities.',
                'Secure screw lid helps reduce accidental spills when the bottle is carried in a backpack or work bag.',
                '750 ml capacity offers a practical amount of water for commuting, office use and short trips.',
                'Wide opening makes filling and routine cleaning easier with common bottle brushes.',
                'Reusable bottle design is suitable for everyday hydration at work, school or while travelling.',
            ],
            'description' => 'A reusable insulated travel bottle made for everyday hydration. The stainless steel body, secure lid and practical capacity make it suitable for commuting, office use and outdoor activities. Clean and dry the bottle after use.',
            'backend_keywords' => 'commuter hydration reusable drinkware outdoor flask',
            'image_count' => 5,
        ]);

        $result = app(ListingOptimizationService::class)->analyze($version);
        $this->assertSame('GOOD', $result['status']);
        $this->assertSame(0, $result['blocker_count']);
        $this->assertSame(0, $result['warning_count']);
    }
}
