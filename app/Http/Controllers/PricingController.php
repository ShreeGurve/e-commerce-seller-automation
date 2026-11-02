<?php
namespace App\Http\Controllers;

use App\Models\{CompetitorPrice, Marketplace, PriceHistory, Product};
use App\Services\{ActivityLogger, PricingService};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PricingController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    private const SEVERITY = ['LOSS' => 0, 'NO_BREAK_EVEN' => 1, 'BELOW_MIN' => 2, 'BELOW_TARGET' => 3, 'NO_PRICE' => 4, 'OK' => 5];

    public function index(Request $request)
    {
        $marketplaces = Marketplace::active()->get();
        $mp = $marketplaces->firstWhere('id', (int) $request->marketplace_id) ?? $marketplaces->first();
        abort_unless($mp, 404);
        $products = Product::with('currentCosts')->where('status', 'active')->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('sku', 'like', "%$s%")->orWhere('name', 'like', "%$s%")))
            ->orderBy('sku')->limit(300)->get();
        $rows = $products->map(fn ($p) => $this->pricing->analyze($p, $mp));
        $counts = $rows->countBy('status');
        if ($request->status) $rows = $rows->where('status', $request->status);
        $rows = $rows->sortBy(fn ($r) => self::SEVERITY[$r['status']] ?? 9)->values();
        $page = max((int) $request->page, 1); $per = 25;
        $paged = new LengthAwarePaginator($rows->slice(($page - 1) * $per, $per)->values(), $rows->count(), $per, $page, ['path' => $request->url(), 'query' => $request->query()]);
        return view('pricing.index', ['rows' => $paged, 'mp' => $mp, 'marketplaces' => $marketplaces, 'counts' => $counts, 'noFees' => $products->isNotEmpty() && $rows->isNotEmpty() && $rows->every(fn ($r) => $r['no_fees']), 'capped' => $products->count() >= 300]);
    }

    public function show(Request $request, Product $product, Marketplace $marketplace)
    {
        $product->load('currentCosts');
        $a = $this->pricing->analyze($product, $marketplace, $request->filled('price') && is_numeric($request->price) ? (string) $request->price : null);
        $history = PriceHistory::with('user:id,name')->where('product_id', $product->id)->where('marketplace_id', $marketplace->id)->orderByDesc('effective_on')->orderByDesc('id')->limit(40)->get();
        $comps = CompetitorPrice::where('product_id', $product->id)->where('marketplace_id', $marketplace->id)->orderByDesc('captured_on')->orderByDesc('id')->limit(60)->get();
        return view('pricing.show', ['a' => $a, 'p' => $product, 'mp' => $marketplace, 'history' => $history, 'comps' => $comps, 'whatIf' => $request->price]);
    }

    public function recordPrice(Request $request, Product $product, Marketplace $marketplace)
    {
        $d = $request->validate(['price' => 'required|numeric|gt:0|max:9999999', 'reason' => 'nullable|string|max:255', 'effective_on' => 'nullable|date|before_or_equal:today']);
        $this->pricing->recordPrice($product, $marketplace->id, (string) $d['price'], $d['reason'] ?? null, $d['effective_on'] ?? null, $request->user());
        return back()->with('success', 'Price recorded. (Change it on the marketplace yourself: this system never edits marketplace prices.)');
    }

    public function competitor(Request $request, Product $product, Marketplace $marketplace)
    {
        $d = $request->validate(['competitor_name' => 'required|string|max:120', 'competitor_price' => 'required|numeric|gt:0|max:9999999', 'captured_on' => 'required|date|before_or_equal:today',
            'source' => 'nullable|string|max:30', 'url' => 'nullable|url|max:500']);
        $this->pricing->addCompetitor($product, $marketplace, trim($d['competitor_name']), (string) $d['competitor_price'], $d['captured_on'], $d['source'] ?? null, $d['url'] ?? null, $request->user());
        return back()->with('success', 'Competitor price saved.');
    }

    public function deleteCompetitor(CompetitorPrice $competitorPrice)
    {
        $competitorPrice->delete();
        ActivityLogger::log('competitor_price.deleted', null, ['competitor' => $competitorPrice->competitor_name, 'price' => (string) $competitorPrice->competitor_price]);
        return back()->with('success', 'Removed.');
    }
}
