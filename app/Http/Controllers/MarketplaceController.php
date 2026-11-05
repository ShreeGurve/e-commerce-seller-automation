<?php
namespace App\Http\Controllers;

use App\Marketplaces\MarketplaceManager;
use App\Models\{DataImport, Marketplace, Order, Product, ProductMarketplace, ProductReturn};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    public function show(string $code, MarketplaceManager $markets)
    {
        $mp = Marketplace::where('code', $code)->where('is_active', true)->firstOrFail();
        $since = now()->subDays(30);
        $live = Order::where('marketplace_id', $mp->id)->where('source', '!=', 'TEST')->where('status', '!=', 'CANCELLED');
        $orders30 = (clone $live)->where('order_date', '>=', $since);
        $units30 = (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.marketplace_id', $mp->id)->where('orders.source', '!=', 'TEST')->where('orders.status', '!=', 'CANCELLED')->whereNull('orders.deleted_at')->where('orders.order_date', '>=', $since)->sum('order_items.quantity');
        $returns30 = ProductReturn::where('marketplace_id', $mp->id)->where('return_date', '>=', $since->toDateString())->count();
        $count30 = (clone $orders30)->count();

        return view('marketplaces.show', [
            'mp' => $mp, 'adapter' => $markets->for($mp),
            'orders30' => $count30, 'sales30' => (float) (clone $orders30)->sum('total_amount'), 'units30' => $units30,
            'returns30' => $returns30, 'returnRate' => $count30 ? round($returns30 / $count30 * 100, 1) : 0,
            'mapped' => ProductMarketplace::where('marketplace_id', $mp->id)->count(),
            'unmapped' => Product::where('status', 'active')->whereDoesntHave('marketplaces', fn ($q) => $q->where('marketplace_id', $mp->id))->count(),
            'recentOrders' => Order::where('marketplace_id', $mp->id)->where('source', '!=', 'TEST')->latest('order_date')->limit(8)->get(),
            'recentImports' => DataImport::where('marketplace_id', $mp->id)->latest()->limit(5)->get(),
        ]);
    }

    public function operations(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'marketplace_id' => 'nullable|integer|exists:marketplaces,id',
            'listing_status' => 'nullable|in:draft,active,inactive,suppressed',
        ]);

        $query = ProductMarketplace::with(['product', 'marketplace'])
            ->when($filters['marketplace_id'] ?? null, fn ($q, $id) => $q->where('marketplace_id', $id))
            ->when($filters['listing_status'] ?? null, fn ($q, $status) => $q->where('listing_status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('marketplace_sku', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($p) => $p->where('sku', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
                });
            });

        return view('marketplaces.operations', [
            'mappings' => $query->latest()->paginate(20)->withQueryString(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'marketplaces' => Marketplace::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'mappedCount' => ProductMarketplace::count(),
            'activeProducts' => Product::where('status', 'active')->count(),
            'unmappedProducts' => Product::where('status', 'active')->whereDoesntHave('marketplaces')->count(),
            'draftListings' => ProductMarketplace::where('listing_status', 'draft')->count(),
            'liveListings' => ProductMarketplace::where('listing_status', 'active')->count(),
        ]);
    }

    public function storeMapping(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'marketplace_id' => 'required|integer|exists:marketplaces,id',
            'marketplace_sku' => [
                'required', 'string', 'max:120',
                Rule::unique('product_marketplace', 'marketplace_sku')
                    ->where(fn ($query) => $query->where('marketplace_id', $request->input('marketplace_id'))),
            ],
            'external_product_id' => 'nullable|string|max:60',
            'fulfillment_sku' => 'nullable|string|max:60',
            'listing_status' => 'required|in:draft,active,inactive,suppressed',
        ]);

        ProductMarketplace::create($data);
        return back()->with('success', 'Local marketplace SKU mapping created. No marketplace listing was published.');
    }

    public function destroyMapping(ProductMarketplace $mapping)
    {
        $mapping->delete();
        return back()->with('success', 'Local marketplace SKU mapping removed. No marketplace listing was changed.');
    }
}
