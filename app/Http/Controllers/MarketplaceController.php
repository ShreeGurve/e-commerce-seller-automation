<?php
namespace App\Http\Controllers;

use App\Marketplaces\MarketplaceManager;
use App\Models\{DataImport, Marketplace, Order, Product, ProductMarketplace, ProductReturn};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    public function show(string $code, MarketplaceManager $markets)
    {
        $mp = Marketplace::where('code', $code)->where('is_active', true)->firstOrFail();
        $since = now()->subDays(30);
        $live = Order::where('marketplace_id', $mp->id)->where('status', '!=', 'CANCELLED');
        $orders30 = (clone $live)->where('order_date', '>=', $since);
        $units30 = (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.marketplace_id', $mp->id)->where('orders.status', '!=', 'CANCELLED')->whereNull('orders.deleted_at')->where('orders.order_date', '>=', $since)->sum('order_items.quantity');
        $returns30 = ProductReturn::where('marketplace_id', $mp->id)->where('return_date', '>=', $since->toDateString())->count();
        $count30 = (clone $orders30)->count();

        return view('marketplaces.show', [
            'mp' => $mp, 'adapter' => $markets->for($mp),
            'orders30' => $count30, 'sales30' => (float) (clone $orders30)->sum('total_amount'), 'units30' => $units30,
            'returns30' => $returns30, 'returnRate' => $count30 ? round($returns30 / $count30 * 100, 1) : 0,
            'mapped' => ProductMarketplace::where('marketplace_id', $mp->id)->count(),
            'unmapped' => Product::where('status', 'active')->whereDoesntHave('marketplaces', fn ($q) => $q->where('marketplace_id', $mp->id))->count(),
            'recentOrders' => Order::where('marketplace_id', $mp->id)->latest('order_date')->limit(8)->get(),
            'recentImports' => DataImport::where('marketplace_id', $mp->id)->latest()->limit(5)->get(),
        ]);
    }

    /**
     * Internal marketplace operations workspace. This page uses local catalogue
     * data only; it never calls or writes to a marketplace API.
     */
    public function operations(Request $request)
    {
        $marketplaces = Marketplace::active()->get();
        $query = ProductMarketplace::with(['product:id,sku,name,status', 'marketplace:id,name,code'])
            ->when($request->filled('marketplace_id'), fn ($q) => $q->where('marketplace_id', $request->integer('marketplace_id')))
            ->when($request->filled('listing_status'), fn ($q) => $q->where('listing_status', $request->string('listing_status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->toString()) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('marketplace_sku', 'like', $term)
                        ->orWhere('external_product_id', 'like', $term)
                        ->orWhereHas('product', fn ($p) => $p->where('sku', 'like', $term)->orWhere('name', 'like', $term));
                });
            });

        $mappings = $query->latest('updated_at')->paginate(25)->withQueryString();
        $activeProducts = Product::where('status', 'active')->count();
        $mappedProducts = Product::where('status', 'active')->whereHas('marketplaces')->count();

        return view('marketplaces.operations', [
            'marketplaces' => $marketplaces,
            'mappings' => $mappings,
            'products' => Product::where('status', 'active')->orderBy('sku')->get(['id', 'sku', 'name']),
            'mappedCount' => ProductMarketplace::count(),
            'activeProducts' => $activeProducts,
            'unmappedProducts' => max(0, $activeProducts - $mappedProducts),
            'draftListings' => \App\Models\Listing::where('status', 'DRAFT')->count(),
            'liveListings' => \App\Models\Listing::where('status', 'LIVE')->count(),
        ]);
    }

    public function storeMapping(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'marketplace_id' => ['required', 'integer', 'exists:marketplaces,id'],
            'marketplace_sku' => ['required', 'string', 'max:120',
                Rule::unique('product_marketplace', 'marketplace_sku')->where('marketplace_id', $request->input('marketplace_id'))],
            'external_product_id' => ['nullable', 'string', 'max:60'],
            'fulfillment_sku' => ['nullable', 'string', 'max:60'],
            'listing_status' => ['required', Rule::in(['active', 'inactive', 'suppressed', 'draft'])],
        ]);

        $duplicate = ProductMarketplace::where('product_id', $data['product_id'])
            ->where('marketplace_id', $data['marketplace_id'])->exists();
        if ($duplicate) {
            return back()->withInput()->withErrors(['product_id' => 'This product is already mapped to this marketplace. Edit the existing mapping from the product page instead.']);
        }

        $mapping = ProductMarketplace::create($data);
        ActivityLogger::log('product.marketplace_mapped', $mapping->product, null, [
            'marketplace_id' => $mapping->marketplace_id,
            'marketplace_sku' => $mapping->marketplace_sku,
            'external_product_id' => $mapping->external_product_id,
        ]);

        return redirect()->route('marketplaces.operations')->with('success', 'Marketplace SKU mapping saved. No marketplace API was called.');
    }

    public function destroyMapping(ProductMarketplace $mapping)
    {
        $old = $mapping->only(['product_id', 'marketplace_id', 'marketplace_sku', 'external_product_id']);
        ActivityLogger::log('product.marketplace_unmapped', $mapping->product, $old);
        $mapping->delete();

        return redirect()->route('marketplaces.operations')->with('success', 'Marketplace mapping removed from the local catalogue.');
    }

}
