<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\ListingOptimizationAudit;
use App\Services\ListingOptimizationService;
use Illuminate\Http\Request;

class ListingOptimizationController extends Controller
{
    public function index(Request $request)
    {
        $audits = ListingOptimizationAudit::query()->with(['listing.product:id,sku,name', 'listing.marketplace', 'version:id,version_no'])
            ->latest('id')->paginate(25);
        $listings = Listing::query()->with(['product:id,sku,name', 'marketplace', 'current:id,listing_id,version_no'])
            ->whereNotNull('current_version_id')->latest('updated_at')->limit(100)->get();

        return view('listings.optimization', compact('audits', 'listings'));
    }

    public function run(Request $request, Listing $listing, ListingOptimizationService $service)
    {
        $audit = $service->audit($listing, $request->user()?->id);

        return redirect()->route('listings.optimization')->with('success', 'Optimization scan completed: '.$audit->status.' ('.$audit->issue_count.' issue(s)).');
    }
}
