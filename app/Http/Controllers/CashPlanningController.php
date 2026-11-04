<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CashPlanningController extends Controller
{
    private const SALES_STATUSES = ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'lookback_days' => 'nullable|integer|in:30,60,90',
            'horizon_days' => 'nullable|integer|in:30,60,90',
            'collection_rate' => 'nullable|numeric|between:0,100',
        ]);
        $lookback = (int) ($filters['lookback_days'] ?? 60);
        $horizon = (int) ($filters['horizon_days'] ?? 30);
        $collectionRate = (float) ($filters['collection_rate'] ?? 80);
        $from = now()->subDays($lookback - 1)->startOfDay();

        $sales = Order::query()->whereBetween('order_date', [$from, now()->endOfDay()])
            ->whereIn('status', self::SALES_STATUSES)->where('source', '<>', 'TEST')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue, COUNT(*) as order_count')->first();
        $dailyRevenue = (float) ($sales->revenue ?? 0) / max(1, $lookback);
        $forecastRevenue = $dailyRevenue * $horizon;
        $expectedCollections = $forecastRevenue * ($collectionRate / 100);

        $openPurchases = Purchase::query()->where('status', 'ORDERED')->with('supplier')
            ->whereNull('deleted_at')->orderBy('expected_delivery_date')->get();
        $purchaseCommitments = $openPurchases->sum(fn ($po) => max(0, (float) $po->total_amount - (float) $po->amount_paid));
        $dueInHorizon = $openPurchases->filter(fn ($po) => !$po->expected_delivery_date || $po->expected_delivery_date->lte(now()->addDays($horizon)->endOfDay()))
            ->sum(fn ($po) => max(0, (float) $po->total_amount - (float) $po->amount_paid));
        $overdueCommitments = $openPurchases->filter(fn ($po) => $po->expected_delivery_date && $po->expected_delivery_date->lt(today()))
            ->sum(fn ($po) => max(0, (float) $po->total_amount - (float) $po->amount_paid));

        $outstandingByProduct = PurchaseItem::query()->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'ORDERED')->whereNull('purchases.deleted_at')
            ->select('purchase_items.product_id')->selectRaw('SUM(GREATEST(purchase_items.quantity - purchase_items.received_qty, 0)) as qty')
            ->groupBy('purchase_items.product_id')->pluck('qty', 'product_id');
        $demand = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$from, now()->endOfDay()])->whereIn('orders.status', self::SALES_STATUSES)
            ->where('orders.source', '<>', 'TEST')->select('order_items.product_id')
            ->selectRaw('SUM(order_items.quantity) as units')->groupBy('order_items.product_id')->pluck('units', 'product_id');

        $replenishment = Product::query()->with(['inventory', 'currentCosts'])->where('status', 'active')->get()->map(function ($product) use ($demand, $outstandingByProduct, $lookback, $horizon) {
            $dailyUnits = (float) ($demand[$product->id] ?? 0) / max(1, $lookback);
            $inventory = $product->inventory;
            $available = (int) ($inventory?->available_qty ?? 0);
            $reserved = (int) ($inventory?->reserved_qty ?? 0);
            $onOrder = (int) ($outstandingByProduct[$product->id] ?? 0);
            $targetUnits = (int) ceil($dailyUnits * min($horizon, 30));
            $suggested = max(0, $targetUnits - max(0, $available - $reserved) - $onOrder);
            $unitCost = (float) $product->totalUnitCost();
            return (object) ['product' => $product, 'daily_units' => $dailyUnits, 'available' => $available,
                'reserved' => $reserved, 'on_order' => $onOrder, 'suggested' => $suggested,
                'unit_cost' => $unitCost, 'investment' => $suggested * $unitCost];
        })->filter(fn ($row) => $row->suggested > 0)->sortByDesc('investment')->values();

        $reorderInvestment = (float) $replenishment->sum('investment');
        $netPlanningPosition = $expectedCollections - $dueInHorizon - $reorderInvestment;

        return view('cash-planning.index', compact('lookback', 'horizon', 'collectionRate', 'sales', 'dailyRevenue',
            'forecastRevenue', 'expectedCollections', 'openPurchases', 'purchaseCommitments', 'dueInHorizon',
            'overdueCommitments', 'replenishment', 'reorderInvestment', 'netPlanningPosition'));
    }
}
