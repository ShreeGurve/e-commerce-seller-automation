<?php

namespace App\Http\Controllers;

use App\Models\{Listing, MarketplaceListingIssue};
use App\Services\MarketplaceListingComplianceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceListingComplianceController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketplaceListingIssue::query()->with(['listing.product:id,sku,name', 'listing.marketplace:id,name'])->latest('id');
        if ($request->filled('status')) $query->where('status', strtoupper((string) $request->string('status')));
        if ($request->filled('severity')) $query->where('severity', strtoupper((string) $request->string('severity')));
        if ($request->boolean('suppression_only')) $query->where('is_suppression', true);
        $issues = $query->paginate(25)->withQueryString();
        $stats = [
            'open' => MarketplaceListingIssue::whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
            'suppression' => MarketplaceListingIssue::where('is_suppression', true)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
            'critical' => MarketplaceListingIssue::whereIn('severity', ['CRITICAL', 'BLOCKER'])->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
            'resolved' => MarketplaceListingIssue::where('status', 'RESOLVED')->count(),
        ];
        $listings = Listing::query()->with(['product:id,sku,name', 'marketplace:id,name'])->latest('id')->limit(200)->get();
        return view('listings.compliance', compact('issues', 'stats', 'listings'));
    }

    public function store(Request $request, MarketplaceListingComplianceService $service)
    {
        $data = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'], 'listing_version_id' => ['nullable', 'integer', 'exists:listing_versions,id'],
            'external_issue_id' => ['nullable', 'string', 'max:120'], 'issue_code' => ['required', 'string', 'max:100'],
            'issue_type' => ['required', Rule::in(['COMPLIANCE', 'CONTENT', 'IMAGE', 'DOCUMENTATION', 'IDENTITY', 'PRODUCT', 'OTHER'])],
            'severity' => ['required', Rule::in(['INFO', 'WARNING', 'CRITICAL', 'BLOCKER'])],
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', Rule::in(['MANUAL', 'IMPORT'])], 'is_suppression' => ['nullable', 'boolean'],
            'due_at' => ['nullable', 'date'], 'evidence' => ['nullable', 'array', 'max:10'],
            'evidence.*.label' => ['required_with:evidence', 'string', 'max:160'], 'evidence.*.reference' => ['required_with:evidence', 'string', 'max:500'], 'evidence.*.note' => ['nullable', 'string', 'max:500'],
        ]);
        $listing = Listing::findOrFail($data['listing_id']);
        $service->report($listing, $data, $request->user()?->id);
        return back()->with('success', 'Marketplace listing issue recorded.');
    }

    public function update(Request $request, MarketplaceListingIssue $issue, MarketplaceListingComplianceService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['OPEN', 'IN_PROGRESS', 'RESOLVED', 'DISMISSED'])],
            'note' => ['nullable', 'string', 'max:5000'], 'evidence' => ['nullable', 'array', 'max:10'],
            'evidence.*.label' => ['required_with:evidence', 'string', 'max:160'], 'evidence.*.reference' => ['required_with:evidence', 'string', 'max:500'], 'evidence.*.note' => ['nullable', 'string', 'max:500']]);
        $service->transition($issue, $data['status'], $data['note'] ?? null, $data['evidence'] ?? [], $request->user()?->id);
        return back()->with('success', 'Issue status updated and audit event recorded.');
    }

    public function recheck(Request $request, Listing $listing, MarketplaceListingComplianceService $service)
    {
        $check = $service->recheck($listing, $request->user()?->id);
        return back()->with('success', 'Local issue-register recheck: '.$check->result.' ('.$check->open_issues.' open issues).');
    }
}
