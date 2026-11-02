<?php
namespace App\Http\Controllers;

use App\Models\{Keyword, Listing, ListingScoreRule, ListingVersion, Marketplace, Product};
use App\Services\{ActivityLogger, ListingService};
use App\Support\TextDiff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    public function __construct(private ListingService $service) {}

    public function index(Request $request)
    {
        $listings = Listing::with(['product:id,sku,name', 'marketplace', 'current:id,version_no,score,created_at', 'published:id,version_no'])
            ->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->q, fn ($q, $s) => $q->whereHas('product', fn ($p) => $p->where('sku', 'like', "%$s%")->orWhere('name', 'like', "%$s%")))
            ->when($request->low, fn ($q) => $q->whereHas('current', fn ($c) => $c->where('score', '<', 70)))
            ->latest('updated_at')->paginate(25)->withQueryString();
        return view('listings.index', ['listings' => $listings, 'marketplaces' => Marketplace::active()->get()]);
    }

    public function create(Request $request)
    {
        if ($request->filled(['product_id', 'marketplace_id']) && ($l = Listing::where('product_id', $request->product_id)->where('marketplace_id', $request->marketplace_id)->first())) {
            return redirect()->route('listings.edit', $l)->with('error', 'A listing already exists for that product and channel. Saving adds a new version.');
        }
        $product = $request->product_id ? Product::find($request->product_id) : null;
        return view('listings.create', ['products' => Product::orderBy('sku')->get(['id', 'sku', 'name']), 'marketplaces' => Marketplace::active()->get(), 'product' => $product,
            'v' => new ListingVersion(['title' => $product?->name, 'bullets' => [], 'image_count' => 0])]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request) + $request->validate(['product_id' => 'required|exists:products,id', 'marketplace_id' => 'required|exists:marketplaces,id']);
        $listing = $this->service->save(Product::findOrFail($d['product_id']), Marketplace::findOrFail($d['marketplace_id']), $d, $request->user(), $d['change_note'] ?? 'First version');
        return redirect()->route('listings.show', $listing)->with('success', 'Listing saved as version ' . $listing->fresh()->current->version_no . '.');
    }

    public function show(Listing $listing)
    {
        $listing->load(['product.brand', 'marketplace', 'current', 'published']);
        $versions = $listing->versions()->with('author:id,name')->get();
        $v = $listing->current;
        $text = $v ? mb_strtolower($v->title . ' ' . implode(' ', $v->bullets ?? []) . ' ' . $v->description . ' ' . $v->backend_keywords) : '';
        $keywords = Keyword::where('product_id', $listing->product_id)->where('marketplace_id', $listing->marketplace_id)->orderByRaw("CASE keyword_type WHEN 'PRIMARY' THEN 1 WHEN 'SECONDARY' THEN 2 WHEN 'LONG_TAIL' THEN 3 WHEN 'BACKEND' THEN 4 WHEN 'AEO' THEN 5 WHEN 'SEO' THEN 6 ELSE 7 END")->get()
            ->map(fn ($k) => ['k' => $k, 'found' => mb_stripos($text, mb_strtolower($k->keyword)) !== false]);
        return view('listings.show', compact('listing', 'versions', 'keywords'));
    }

    public function edit(Listing $listing)
    {
        $listing->load(['product', 'marketplace', 'current']);
        return view('listings.edit', ['listing' => $listing, 'v' => $listing->current]);
    }

    public function update(Request $request, Listing $listing)
    {
        $d = $this->validated($request);
        $this->service->save($listing->product, $listing->marketplace, $d, $request->user(), $d['change_note'] ?? null);
        return redirect()->route('listings.show', $listing)->with('success', 'New version saved.');
    }

    public function compare(Request $request, Listing $listing)
    {
        $d = $request->validate(['a' => 'required|integer', 'b' => 'required|integer']);
        $a = ListingVersion::where('listing_id', $listing->id)->findOrFail($d['a']); $b = ListingVersion::where('listing_id', $listing->id)->findOrFail($d['b']);
        if ($a->version_no > $b->version_no) [$a, $b] = [$b, $a];          // older on the left
        $diff = ['title' => TextDiff::words($a->title, $b->title), 'description' => TextDiff::words($a->description, $b->description),
            'backend' => TextDiff::words($a->backend_keywords, $b->backend_keywords), 'features' => TextDiff::words($a->special_features, $b->special_features),
            'aplus' => TextDiff::words($a->aplus_content, $b->aplus_content), 'bullets' => []];
        for ($i = 0; $i < max(count($a->bullets ?? []), count($b->bullets ?? [])); $i++) $diff['bullets'][] = TextDiff::words(($a->bullets ?? [])[$i] ?? '', ($b->bullets ?? [])[$i] ?? '');
        return view('listings.compare', compact('listing', 'a', 'b', 'diff'));
    }

    public function publish(Listing $listing, ListingVersion $version)
    {
        $this->service->publish($listing, $version);
        return back()->with('success', "Version {$version->version_no} recorded as live on {$listing->marketplace->name}. (Apply it on the marketplace yourself.)");
    }

    public function restore(Request $request, Listing $listing, ListingVersion $version)
    {
        $this->service->restore($listing, $version, $request->user());
        return redirect()->route('listings.show', $listing)->with('success', "Version {$version->version_no} restored as a new version.");
    }

    public function status(Request $request, Listing $listing)
    {
        $d = $request->validate(['status' => ['required', Rule::in(array_keys(Listing::STATUSES))]]);
        $old = $listing->status; $listing->update($d);
        ActivityLogger::log('listing.status_changed', $listing, ['status' => $old], ['status' => $d['status']]);
        return back()->with('success', 'Status updated.');
    }

    public function rescore(Listing $listing)
    {
        $this->service->rescore($listing);
        return back()->with('success', 'Score recalculated with your current keywords and rules.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:500', 'bullets' => 'nullable|array|max:10', 'bullets.*' => 'nullable|string|max:1000', 'description' => 'nullable|string|max:20000',
            'backend_keywords' => 'nullable|string|max:500', 'special_features' => 'nullable|string|max:2000', 'aplus_content' => 'nullable|string|max:50000',
            'image_count' => 'nullable|integer|min:0|max:100', 'has_video' => 'nullable|boolean', 'category_path' => 'nullable|string|max:255', 'brand' => 'nullable|string|max:120', 'change_note' => 'nullable|string|max:255']);
    }
}
