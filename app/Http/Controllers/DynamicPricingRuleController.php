<?php

namespace App\Http\Controllers;

use App\Models\DynamicPricingRule;
use App\Models\Marketplace;
use App\Models\Product;
use App\Services\DynamicPricingService;
use Illuminate\Http\Request;

class DynamicPricingRuleController extends Controller
{
    public function index(Request $request)
    {
        $query = DynamicPricingRule::with(['product:id,sku,name', 'marketplace:id,name'])->latest();
        if (! $request->user()->isSuperAdmin()) {
            $query->where('business_id', $request->user()->business_id);
        }
        $rules = $query->paginate(25)->withQueryString();
        $products = Product::query()->where('status', 'active')->orderBy('sku')->limit(500)->get(['id', 'sku', 'name']);
        $marketplaces = Marketplace::active()->get(['id', 'name']);

        return view('pricing.dynamic-rules', compact('rules', 'products', 'marketplaces'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $product = Product::with('brand')->findOrFail($data['product_id']);
        $this->assertProductBusiness($request, $product);
        $businessId = $request->user()->isSuperAdmin() ? ($product->brand?->business_id ?? $request->user()->business_id) : $request->user()->business_id;
        DynamicPricingRule::updateOrCreate(
            ['product_id' => $product->id, 'marketplace_id' => $data['marketplace_id']],
            $data + ['enabled' => (bool) ($data['enabled'] ?? false), 'business_id' => $businessId, 'created_by' => $request->user()->id]
        );

        return back()->with('success', 'Dynamic pricing rule saved. It will create internal recommendations only.');
    }

    public function update(Request $request, DynamicPricingRule $dynamicPricingRule)
    {
        $this->assertRuleBusiness($request, $dynamicPricingRule);
        $data = $this->validated($request, true);
        $dynamicPricingRule->update($data);

        return back()->with('success', 'Dynamic pricing rule updated.');
    }

    public function destroy(Request $request, DynamicPricingRule $dynamicPricingRule)
    {
        $this->assertRuleBusiness($request, $dynamicPricingRule);
        $dynamicPricingRule->update(['enabled' => false]);

        return back()->with('success', 'Rule disabled. Historical recommendations are unchanged.');
    }

    public function run(Request $request, DynamicPricingService $service)
    {
        $businessId = $request->user()->isSuperAdmin() ? null : $request->user()->business_id;
        $run = $service->run($businessId);

        return back()->with($run->status === 'COMPLETED' ? 'success' : 'error', $run->summary);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'product_id' => [$updating ? 'sometimes' : 'required', 'integer', 'exists:products,id'],
            'marketplace_id' => [$updating ? 'sometimes' : 'required', 'integer', 'exists:marketplaces,id'],
            'strategy' => 'required|in:TARGET_MARGIN,COMPETITOR_MATCH,COMPETITOR_UNDERCUT,COMPETITOR_PREMIUM',
            'floor_price' => 'nullable|numeric|gt:0|max:9999999',
            'ceiling_price' => 'nullable|numeric|gt:0|max:9999999',
            'minimum_margin_percent' => 'required|numeric|min:0|max:95',
            'competitor_adjustment_percent' => 'required|numeric|min:0|max:20',
            'enabled' => 'sometimes|boolean',
        ]);
    }

    private function assertProductBusiness(Request $request, Product $product): void
    {
        if (! $request->user()->isSuperAdmin()) {
            abort_unless($product->brand && (int) $product->brand->business_id === (int) $request->user()->business_id, 404);
        }
    }

    private function assertRuleBusiness(Request $request, DynamicPricingRule $rule): void
    {
        if (! $request->user()->isSuperAdmin()) {
            abort_unless($rule->business_id !== null && (int) $rule->business_id === (int) $request->user()->business_id, 404);
        }
    }
}
