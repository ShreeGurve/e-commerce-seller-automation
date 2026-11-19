<?php

namespace Tests\Feature;

use App\Models\{Listing, Marketplace, MarketplaceListingIssue, Product};
use App\Services\MarketplaceListingComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceListingComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function listing(): Listing
    {
        $product = Product::create(['sku' => 'COMP-'.uniqid(), 'name' => 'Compliance test item']);
        $marketplace = Marketplace::create(['code' => 'test-'.uniqid(), 'name' => 'Test Marketplace', 'is_active' => true]);
        return Listing::create(['product_id' => $product->id, 'marketplace_id' => $marketplace->id, 'status' => 'LIVE']);
    }

    public function test_reported_suppression_issue_is_open_and_has_audit_event(): void
    {
        $listing = $this->listing();
        $issue = app(MarketplaceListingComplianceService::class)->report($listing, [
            'issue_code' => 'IMAGE_POLICY', 'title' => 'Main image rejected', 'issue_type' => 'IMAGE',
            'severity' => 'CRITICAL', 'is_suppression' => true,
            'evidence' => [['label' => 'Seller notice', 'reference' => 'case-123']],
        ]);

        $this->assertSame('OPEN', $issue->status);
        $this->assertTrue($issue->is_suppression);
        $this->assertCount(1, $issue->events);
        $this->assertSame('REPORTED', $issue->events->first()->event_type);
    }

    public function test_resolving_issue_requires_resolution_note_and_preserves_history(): void
    {
        $listing = $this->listing();
        $service = app(MarketplaceListingComplianceService::class);
        $issue = $service->report($listing, ['issue_code' => 'DOC_MISSING', 'title' => 'Document missing']);

        try {
            $service->transition($issue, 'RESOLVED');
            $this->fail('Resolution should require a note.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('note', $exception->errors());
        }

        $resolved = $service->transition($issue, 'RESOLVED', 'Uploaded the requested document.');
        $this->assertSame('RESOLVED', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertCount(2, $resolved->events);
    }

    public function test_recheck_creates_local_snapshot_without_mutating_listing_status(): void
    {
        $listing = $this->listing();
        $service = app(MarketplaceListingComplianceService::class);
        $service->report($listing, ['issue_code' => 'SUPPRESSION', 'title' => 'Listing suppressed', 'is_suppression' => true]);

        $check = $service->recheck($listing);
        $this->assertSame('SUPPRESSED_OR_AT_RISK', $check->result);
        $this->assertSame(1, $check->open_issues);
        $this->assertSame('LIVE', $listing->fresh()->status);
    }
}
