<?php

namespace App\Http\Controllers;

use App\Models\InventoryDemandForecast;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryForecastController extends Controller
{
    private const DEMAND_STATUSES = ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'];

    private function settings(Request $request): array
    {
        $data = $request->validate([
            'lookback_days' => 'nullable|integer|in:30,60,90',
            'horizon_days' => 'nullable|integer|in:7,14,30,60',
            'lead_time_days' => 'nullable|integer|between:0,180',
            'safety_stock_days' => 'nullable|integer|between:0,180',
        ]);

        return [
            'lookback_days' => (int) ($data['lookback_days'] ?? 60),
            'horizon_days' => (int) ($data['horizon_days'] ?? 30),
            'lead_time_days' => (int) ($data['lead_time_days'] ?? 7),
            'safety_stock_days' => (int) ($data['safety_stock_days'] ?? 7),
        ];
    }

    private function demandByProduct(Carbon $from, Carbon $to)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->whereIn('orders.status', self::DEMAND_STATUSES)
            ->where('orders.source', '<>', 'TEST')
            ->select('order_items.product_id')
            ->selectRaw('SUM(order_items.quantity) as units')
            ->groupBy('order_items.product_id')
            ->pluck('units', 'product_id');
    }

    private function outstandingByProduct()
    {
        return PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'ORDERED')
            ->whereNull('purchases.deleted_at')
            ->select('purchase_items.product_id')
            ->selectRaw('SUM(GREATEST(purchase_items.quantity - purchase_items.received_qty, 0)) as outstanding_qty')
            ->groupBy('purchase_items.product_id')
            ->pluck('outstanding_qty', 'product_id');
    }

    private function forecastRows(array $settings)
    {
        $today = now()->startOfDay();
        $from = $today->copy()->subDays($settings['lookback_days'] - 1);
        $sales = $this->demandByProduct($from, $today);
        $incoming = $this->outstandingByProduct();

        return Product::query()->with('inventory')->where('status', 'active')->orderBy('sku')->get()->map(function ($product) use ($settings, $sales, $incoming) {
            $inventory = $product->inventory;
            $units = (int) ($sales[$product->id] ?? 0);
            $daily = $settings['lookback_days'] > 0 ? $units / $settings['lookback_days'] : 0;
            $available = (int) ($inventory?->available_qty ?? 0);
            $reserved = (int) ($inventory?->reserved_qty ?? 0);
            $onOrder = (int) ($incoming[$product->id] ?? 0);
            $stockPosition = max(0, $available - $reserved) + $onOrder;
            $predicted = (int) ceil($daily * $settings['horizon_days']);
            $coverDays = $settings['lead_time_days'] + $settings['safety_stock_days'];
            $targetDemand = (int) ceil($daily * $coverDays);
            $suggested = max(0, $targetDemand - $stockPosition);
            $stockOutDays = $daily > 0 ? (int) floor(max(0, $available - $reserved) / $daily) : null;

            return (object) [
                'product' => $product, 'units_sold' => $units, 'daily_demand' => $daily,
                'predicted_demand' => $predicted, 'available' => $available, 'reserved' => $reserved,
                'on_order' => $onOrder, 'stock_position' => $stockPosition,
                'stock_out_days' => $stockOutDays,
                'stock_out_date' => $stockOutDays !== null ? now()->addDays($stockOutDays)->toDateString() : null,
                'suggested' => $suggested,
                'status' => $daily <= 0 ? 'NO SALES DATA' : ($stockOutDays !== null && $stockOutDays <= $settings['lead_time_days'] ? 'CRITICAL' : ($suggested > 0 ? 'REORDER' : 'COVERED')),
            ];
        });
    }

    public function index(Request $request)
    {
        $settings = $this->settings($request);
        $rows = $this->forecastRows($settings);
        $snapshots = InventoryDemandForecast::with('product')->latest('forecast_date')->latest('id')->limit(100)->get();
        $completed = $snapshots->filter(fn ($s) => $s->forecast_end_date->toDateString() < now()->toDateString());
        $accuracyRows = $completed->map(function ($snapshot) {
            $actual = (int) OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.product_id', $snapshot->product_id)
                ->whereBetween('orders.order_date', [$snapshot->forecast_start_date->copy()->startOfDay()->toDateTimeString(), $snapshot->forecast_end_date->copy()->endOfDay()->toDateTimeString()])
                ->whereIn('orders.status', self::DEMAND_STATUSES)
                ->where('orders.source', '<>', 'TEST')
                ->sum('order_items.quantity');
            $predicted = (int) $snapshot->predicted_demand;
            $accuracy = $actual === 0 ? ($predicted === 0 ? 100 : 0) : max(0, 100 - (abs($actual - $predicted) / $actual) * 100);
            return (object) ['snapshot' => $snapshot, 'actual' => $actual, 'accuracy' => round($accuracy, 1)];
        });

        return view('forecasting.index', [
            'settings' => $settings, 'rows' => $rows,
            'criticalCount' => $rows->where('status', 'CRITICAL')->count(),
            'reorderCount' => $rows->where('suggested', '>', 0)->count(),
            'totalSuggested' => $rows->sum('suggested'),
            'accuracyRows' => $accuracyRows->take(20),
            'averageAccuracy' => $accuracyRows->isNotEmpty() ? round($accuracyRows->avg('accuracy'), 1) : null,
        ]);
    }

    public function snapshot(Request $request)
    {
        $settings = $this->settings($request);
        $rows = $this->forecastRows($settings);
        $start = now()->addDay()->toDateString();
        $end = now()->addDays($settings['horizon_days'])->toDateString();

        DB::transaction(function () use ($rows, $settings, $request, $start, $end) {
            foreach ($rows as $row) {
                InventoryDemandForecast::create([
                    'product_id' => $row->product->id,
                    'forecast_date' => now()->toDateString(),
                    'forecast_start_date' => $start,
                    'forecast_end_date' => $end,
                    'lookback_days' => $settings['lookback_days'],
                    'lead_time_days' => $settings['lead_time_days'],
                    'safety_stock_days' => $settings['safety_stock_days'],
                    'average_daily_demand' => round($row->daily_demand, 4),
                    'predicted_demand' => $row->predicted_demand,
                    'stock_position' => $row->stock_position,
                    'suggested_reorder_qty' => $row->suggested,
                    'generated_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('inventory-forecast.index', $settings)->with('success', 'Forecast snapshot saved. Accuracy will be calculated after the forecast period ends using non-test sales records.');
    }
}
