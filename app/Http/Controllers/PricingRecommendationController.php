<?php

namespace App\Http\Controllers;

use App\Models\Marketplace;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\PricingRecommendation;
use App\Services\ActivityLogger;
use App\Services\PricingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingRecommendationController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function index(Request $request)
    {
        $query = PricingRecommendation::with(['product', 'marketplace', 'requester', 'reviewer'])
            ->latest();
        if (!$request->user()->isSuperAdmin()) {
            $query->where('business_id', $request->user()->business_id);
        }
        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected', 'applied'], true)) {
            $query->where('status', $request->status);
        }
        $recommendations = $query->paginate(20)->withQueryString();
        return view('pricing.recommendations', compact('recommendations'));
    }

    public function store(Request $request, Product $product, Marketplace $marketplace)
    {
        $data = $request->validate([
            'proposed_price' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $product->load(['currentCosts', 'marketplaceSettings' => fn ($q) => $q->where('marketplace_id', $marketplace->id)]);
        $analysis = $this->pricing->analyze($product, $marketplace, (string) $data['proposed_price']);
        $proposedPaise = Money::p((string) $data['proposed_price']);
        $minimumPaise = $analysis['min_price'];

        if ($minimumPaise === null) {
            throw ValidationException::withMessages([
                'proposed_price' => 'A safe minimum price cannot be calculated with the current costs and fee rules. Review your fee rules and product costs first.',
            ]);
        }
        if ($proposedPaise < $minimumPaise) {
            throw ValidationException::withMessages([
                'proposed_price' => 'This price is below the calculated minimum of ₹' . number_format($minimumPaise / 100, 2) . '.',
            ]);
        }

        $user = $request->user();
        $brandBusinessId = $product->brand()->value('business_id');
        if (!$user->isSuperAdmin() && $brandBusinessId && (int) $brandBusinessId !== (int) $user->business_id) {
            abort(404);
        }

        $row = PricingRecommendation::create([
            'business_id' => $user->business_id,
            'product_id' => $product->id,
            'marketplace_id' => $marketplace->id,
            'current_price' => $analysis['current'] !== null ? Money::m($analysis['current']) : null,
            'proposed_price' => Money::m($proposedPaise),
            'minimum_price' => Money::m($minimumPaise),
            'expected_profit' => isset($analysis['at']['profit']) ? Money::m((int) round($analysis['at']['profit'])) : null,
            'expected_margin' => $analysis['at']['margin'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        ActivityLogger::log('pricing.recommendation_requested', $row, null, [
            'product_id' => $product->id, 'marketplace_id' => $marketplace->id,
            'proposed_price' => Money::m($proposedPaise),
        ]);

        return redirect()->route('pricing-recommendations.index')
            ->with('success', 'Price recommendation submitted for approval. No marketplace price was changed.');
    }

    public function approve(Request $request, PricingRecommendation $pricingRecommendation)
    {
        $this->ensureReviewer($request);
        $this->ensureBusiness($request, $pricingRecommendation);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($pricingRecommendation, $request, $data) {
            $row = PricingRecommendation::whereKey($pricingRecommendation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'pending', 422, 'Only pending recommendations can be approved.');
            $row->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);
        });

        ActivityLogger::log('pricing.recommendation_approved', $pricingRecommendation);
        return back()->with('success', 'Recommendation approved. It is still not published to the marketplace.');
    }

    public function reject(Request $request, PricingRecommendation $pricingRecommendation)
    {
        $this->ensureReviewer($request);
        $this->ensureBusiness($request, $pricingRecommendation);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($pricingRecommendation, $request, $data) {
            $row = PricingRecommendation::whereKey($pricingRecommendation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'pending', 422, 'Only pending recommendations can be rejected.');
            $row->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'],
            ]);
        });

        ActivityLogger::log('pricing.recommendation_rejected', $pricingRecommendation);
        return back()->with('success', 'Recommendation rejected.');
    }

    public function apply(Request $request, PricingRecommendation $pricingRecommendation)
    {
        $this->ensureBusiness($request, $pricingRecommendation);
        abort_unless($request->user()->can('products.edit'), 403);
        DB::transaction(function () use ($pricingRecommendation, $request) {
            $row = PricingRecommendation::whereKey($pricingRecommendation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'approved', 422, 'Only approved recommendations can be recorded.');
            $product = Product::with(['currentCosts', 'marketplaceSettings' => fn ($q) => $q->where('marketplace_id', $row->marketplace_id)])
                ->findOrFail($row->product_id);
            $marketplace = Marketplace::findOrFail($row->marketplace_id);

            // Re-check the minimum against current costs and fee rules before recording.
            $analysis = $this->pricing->analyze($product, $marketplace, (string) $row->proposed_price);
            $proposedPaise = Money::p((string) $row->proposed_price);
            abort_unless($analysis['min_price'] !== null && $proposedPaise >= $analysis['min_price'], 422,
                'The approved price is no longer above the current minimum. Create a new recommendation after reviewing costs and fees.');

            $previous = $this->pricing->currentPrice($product, $marketplace->id);
            PriceHistory::create([
                'product_id' => $product->id,
                'marketplace_id' => $marketplace->id,
                'price' => $row->proposed_price,
                'previous_price' => $previous['price'] ?? null,
                'reason' => 'Approved recommendation #' . $row->id,
                'source' => 'MANUAL',
                'effective_on' => now()->toDateString(),
                'changed_by' => $request->user()->id,
            ]);
            $row->update(['status' => 'applied', 'applied_at' => now()]);
        });

        ActivityLogger::log('pricing.recommendation_recorded', $pricingRecommendation);
        return back()->with('success', 'Approved price recorded in internal price history. Update the marketplace manually when ready.');
    }

    private function ensureBusiness(Request $request, PricingRecommendation $row): void
    {
        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            abort_unless($row->business_id !== null && $user->business_id !== null
                && (int) $row->business_id === (int) $user->business_id, 404);
        }
    }

    private function ensureReviewer(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->hasPermission('settings.manage') || $user->hasPermission('users.manage'),
            403, 'You do not have permission to review pricing recommendations.');
    }
}
