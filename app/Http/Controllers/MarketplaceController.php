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
}
