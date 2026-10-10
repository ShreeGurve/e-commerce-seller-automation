<?php
namespace App\Http\Controllers;

use App\Models\{Category, FeeRule, Marketplace, Product};
use App\Services\{ActivityLogger, FeeService};
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeRuleController extends Controller
{
    public function index(Request $request, FeeService $fees)
    {
        $rules = FeeRule::with(['marketplace', 'category'])->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->orderBy('marketplace_id')->orderBy('fee_type')->orderByDesc('effective_from')->get();

        $sim = null;   // fee simulator: check your rules against a real product + price
        if ($request->filled(['sim_product', 'sim_marketplace', 'sim_price'])) {
            $p = Product::find($request->sim_product);
            $price = Money::p($request->sim_price); $qty = max((int) $request->input('sim_qty', 1), 1);
            if ($p && $price > 0) {
                $line = $price * $qty; $rate = (float) $p->gst_rate;
                $tax = (int) round($line * $rate / (100 + $rate));
                $sim = ['product' => $p, 'line' => $line, 'tax' => $tax, 'lines' => $fees->estimate($p, (int) $request->sim_marketplace, $line, $tax, $qty, now())];
            }
        }
        return view('fees.index', ['rules' => $rules, 'marketplaces' => Marketplace::active()->get(), 'products' => Product::orderBy('sku')->get(['id', 'sku', 'name']), 'sim' => $sim]);
    }

    public function create() { return view('fees.create', $this->form(new FeeRule(['is_active' => true, 'applies_on' => 'PRICE', 'effective_from' => now()]))); }

    public function store(Request $request)
    {
        $rule = FeeRule::create($this->validated($request) + ['created_by' => $request->user()->id]);
        ActivityLogger::log('fee_rule.created', $rule, null, $rule->only(['fee_type', 'percentage', 'fixed_amount', 'marketplace_id']));
        return redirect()->route('fees.index')->with('success', 'Fee rule saved. Run "Recalculate" on the Profit page to apply it to existing orders.');
    }

    public function edit(FeeRule $feeRule) { return view('fees.edit', $this->form($feeRule)); }

    public function update(Request $request, FeeRule $feeRule)
    {
        $feeRule->fill($this->validated($request));
        $dirty = $feeRule->getDirty(); $old = array_intersect_key($feeRule->getOriginal(), $dirty);
        $feeRule->save();
        if ($dirty) ActivityLogger::log('fee_rule.updated', $feeRule, $old, $dirty);
        return redirect()->route('fees.index')->with('success', 'Fee rule updated. Run "Recalculate" on the Profit page to apply it to existing orders.');
    }

    public function destroy(FeeRule $feeRule)
    {
        $feeRule->delete();
        ActivityLogger::log('fee_rule.deleted', $feeRule, $feeRule->only(['fee_type', 'percentage', 'fixed_amount']));
        return redirect()->route('fees.index')->with('success', 'Fee rule deleted.');
    }

    private function form(FeeRule $rule): array
    {
        return ['rule' => $rule, 'marketplaces' => Marketplace::active()->get(), 'categories' => Category::orderBy('name')->get()];
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'marketplace_id' => 'required|exists:marketplaces,id', 'category_id' => 'nullable|exists:categories,id',
            'fee_type' => ['required', Rule::in(array_keys(FeeRule::TYPES))], 'name' => 'nullable|string|max:255',
            'percentage' => 'nullable|numeric|between:0,100', 'fixed_amount' => 'nullable|numeric|min:0|max:9999999',
            'applies_on' => ['required', Rule::in(array_keys(FeeRule::APPLIES))],
            'weight_min_g' => 'nullable|integer|min:0|max:10000000', 'weight_max_g' => 'nullable|integer|min:0|max:10000000|gte:weight_min_g',
            'effective_from' => 'required|date', 'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        if ((float) ($d['percentage'] ?? 0) <= 0 && (float) ($d['fixed_amount'] ?? 0) <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['percentage' => 'Enter a percentage, a fixed amount, or both.']);
        }
        $d['percentage'] = $d['percentage'] ?? 0; $d['fixed_amount'] = $d['fixed_amount'] ?? 0; $d['is_active'] = $request->boolean('is_active');
        return $d;
    }
}
