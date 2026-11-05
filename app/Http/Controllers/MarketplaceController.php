<?php
namespace App\Http\Controllers;

use App\Marketplaces\MarketplaceManager;
use App\Models\{DataImport, Marketplace, Order, Product, ProductMarketplace, ProductReturn};
use Illuminate\Support\Facades\DB;

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

    /** Internal SKU mapping workspace. This does not publish or sync marketplace data. */
    public function operations(\Illuminate\Http\Request $request)
    {
        $query = ProductMarketplace::with(['product', 'marketplace']);
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('marketplace_sku', 'like', "%{$term}%")
                    ->orWhere('external_product_id', 'like', "%{$term}%")
                    ->orWhereHas('product', function ($product) use ($term) {
                        $product->where('sku', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%");
                    });
            });
        }
        if ($request->filled('marketplace_id')) {
            $query->where('marketplace_id', $request->integer('marketplace_id'));
        }
        if ($request->filled('listing_status')) {
            $query->where('listing_status', $request->input('listing_status'));
        }

        $mappings = $query->latest()->paginate(20)->withQueryString();
        $marketplaces = Marketplace::where('is_active', true)->orderBy('sort_order')->get();
        $products = Product::where('status', 'active')->orderBy('name')->get();
        $mappedCount = ProductMarketplace::count();
        $activeProducts = Product::where('status', 'active')->count();
        $unmappedProducts = Product::where('status', 'active')
            ->whereDoesntHave('marketplaces')->count();
        $draftListings = ProductMarketplace::where('listing_status', 'draft')->count();
        $liveListings = ProductMarketplace::where('listing_status', 'active')->count();

        return view('marketplaces.operations', compact(
            'mappings', 'marketplaces', 'products', 'mappedCount', 'activeProducts',
            'unmappedProducts', 'draftListings', 'liveListings'
        ));
    }

    public function storeMapping(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'marketplace_id' => ['required', 'integer', 'exists:marketplaces,id'],
            'marketplace_sku' => ['required', 'string', 'max:120'],
            'external_product_id' => ['nullable', 'string', 'max:60'],
            'fulfillment_sku' => ['nullable', 'string', 'max:60'],
            'listing_status' => ['required', 'in:draft,active,inactive,suppressed'],
        ]);

        $exists = ProductMarketplace::where('marketplace_id', $data['marketplace_id'])
            ->where('marketplace_sku', $data['marketplace_sku'])->exists();
        if ($exists) {
            return back()->withErrors(['marketplace_sku' => 'This marketplace SKU is already mapped on the selected marketplace.'])->withInput();
        }
        ProductMarketplace::create($data);
        return back()->with('success', 'Local marketplace SKU mapping saved. No marketplace listing was changed.');
    }

    public function destroyMapping(ProductMarketplace $mapping)
    {
        $mapping->delete();
        return back()->with('success', 'Local marketplace SKU mapping removed. No marketplace listing was changed.');
    }

}
