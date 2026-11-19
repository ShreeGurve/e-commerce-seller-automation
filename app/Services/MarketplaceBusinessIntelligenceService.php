<?php

namespace App\Services;

use App\Models\Marketplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceBusinessIntelligenceService
{
    /** Read-only product and marketplace performance intelligence. */
    public function dashboard(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => 'The start date must be before or equal to the end date.']);
        }

        $marketplaceId = $filters['marketplace_id'] ?? null;
        $lowMarginThreshold = (float) ($filters['low_margin_threshold'] ?? 10);
        if ($lowMarginThreshold < 0 || $lowMarginThreshold > 100) {
            throw ValidationException::withMessages(['low_margin_threshold' => 'The margin threshold must be between 0 and 100.']);
        }

        $base = DB::table('order_profits as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereBetween('op.order_date', [$from, $to])
            ->whereNull('o.deleted_at')
            ->where(function ($q) { $q->whereNull('o.source')->orWhere('o.source', '!=', 'TEST'); })
            ->where('o.status', '!=', 'CANCELLED')
            ->when($marketplaceId, fn ($q) => $q->where('op.marketplace_id', $marketplaceId));

        $totals = (clone $base)->selectRaw(
            'COUNT(DISTINCT op.order_id) as orders, SUM(op.qty) as units, SUM(op.revenue) as revenue, '
            .'SUM(COALESCE(op.actual_profit, op.estimated_profit)) as net_profit, '
            .'SUM(CASE WHEN op.actual_profit IS NOT NULL THEN 1 ELSE 0 END) as actual_lines, COUNT(*) as total_lines'
        )->first();

        // Group daily rows in PHP so the report works consistently on SQLite and MySQL.
        $daily = (clone $base)->selectRaw('op.order_date, SUM(op.revenue) as revenue, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit, SUM(op.qty) as units')
            ->groupBy('op.order_date')->orderBy('op.order_date')->get();
        $monthly = [];
        foreach ($daily as $row) {
            $month = substr((string) $row->order_date, 0, 7);
            $monthly[$month] ??= ['month' => $month, 'revenue' => 0.0, 'profit' => 0.0, 'units' => 0];
            $monthly[$month]['revenue'] += (float) $row->revenue;
            $monthly[$month]['profit'] += (float) $row->profit;
            $monthly[$month]['units'] += (int) $row->units;
        }
        $salesTrend = collect(array_values($monthly))->map(function ($row) {
            $row['revenue'] = round($row['revenue'], 2);
            $row['profit'] = round($row['profit'], 2);
            return $row;
        })->values();

        $products = (clone $base)->join('products as p', 'p.id', '=', 'op.product_id')
            ->selectRaw('op.product_id, p.name as product_name, p.sku, SUM(op.qty) as units, SUM(op.revenue) as revenue, '
                .'SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit, '
                .'CASE WHEN SUM(op.revenue) > 0 THEN (SUM(COALESCE(op.actual_profit, op.estimated_profit)) / SUM(op.revenue)) * 100 ELSE 0 END as margin')
            ->groupBy('op.product_id', 'p.name', 'p.sku')->orderByDesc('profit')->limit(100)->get()
            ->map(function ($row) {
                $row->units = (int) $row->units;
                $row->revenue = round((float) $row->revenue, 2);
                $row->profit = round((float) $row->profit, 2);
                $row->margin = round((float) $row->margin, 2);
                return $row;
            });

        $lowMarginProducts = $products->filter(fn ($row) => $row->margin < $lowMarginThreshold)->values();

        $soldUnits = (int) ((clone $base)->sum('op.qty') ?? 0);
        $returnQuery = DB::table('return_items as ri')
            ->join('returns as r', 'r.id', '=', 'ri.return_id')
            ->whereNull('r.deleted_at')->whereBetween('r.return_date', [$from, $to])
            ->when($marketplaceId, fn ($q) => $q->where('r.marketplace_id', $marketplaceId));
        $returnedUnits = (int) (clone $returnQuery)->sum('ri.quantity');
        $returnCount = (int) (clone $returnQuery)->distinct('r.id')->count('r.id');
        $returnRate = $soldUnits > 0 ? round(($returnedUnits / $soldUnits) * 100, 2) : 0.0;

        $returnByProduct = (clone $returnQuery)->join('products as rp', 'rp.id', '=', 'ri.product_id')
            ->selectRaw('ri.product_id, rp.name as product_name, rp.sku, SUM(ri.quantity) as returned_units')
            ->groupBy('ri.product_id', 'rp.name', 'rp.sku')->orderByDesc('returned_units')->limit(20)->get();

        $byMarketplace = (clone $base)->join('marketplaces as m', 'm.id', '=', 'op.marketplace_id')
            ->selectRaw('op.marketplace_id, m.name as marketplace_name, COUNT(DISTINCT op.order_id) as orders, SUM(op.qty) as units, '
                .'SUM(op.revenue) as revenue, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit')
            ->groupBy('op.marketplace_id', 'm.name')->orderByDesc('profit')->get()
            ->map(function ($row) {
                $row->margin = (float) $row->revenue > 0 ? round(((float) $row->profit / (float) $row->revenue) * 100, 2) : 0.0;
                $row->revenue = round((float) $row->revenue, 2);
                $row->profit = round((float) $row->profit, 2);
                return $row;
            });

        return [
            'filters' => ['from' => $from, 'to' => $to, 'marketplace_id' => $marketplaceId, 'low_margin_threshold' => $lowMarginThreshold],
            'totals' => $totals,
            'profit_margin' => (float) ($totals->revenue ?? 0) > 0 ? round(((float) $totals->net_profit / (float) $totals->revenue) * 100, 2) : 0.0,
            'actual_coverage' => (int) ($totals->total_lines ?? 0) > 0 ? round(((int) $totals->actual_lines / (int) $totals->total_lines) * 100, 1) : 0.0,
            'sales_trend' => $salesTrend,
            'top_products' => $products->take(10)->values(),
            'low_margin_products' => $lowMarginProducts,
            'returns' => ['count' => $returnCount, 'units' => $returnedUnits, 'rate' => $returnRate],
            'return_by_product' => $returnByProduct,
            'by_marketplace' => $byMarketplace,
            'marketplaces' => Marketplace::orderBy('name')->get(['id', 'name']),
        ];
    }
}
