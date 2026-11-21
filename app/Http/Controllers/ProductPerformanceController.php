<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPerformanceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('order_profits') && Schema::hasTable('products'), 503, 'Product profitability data is not available.');

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'marketplace_id' => ['nullable', 'integer', 'exists:marketplaces,id'],
            'sort' => ['nullable', 'in:profit,revenue,units,margin'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $marketplaceId = $data['marketplace_id'] ?? null;
        $sort = $data['sort'] ?? 'profit';
        $direction = $data['direction'] ?? 'desc';

        $query = DB::table('order_profits as op')
            ->join('products as p', 'p.id', '=', 'op.product_id')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereBetween('op.order_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('o.source')->orWhere('o.source', '<>', 'TEST');
            })
            ->whereNull('o.deleted_at')
            ->when($marketplaceId, fn ($q) => $q->where('op.marketplace_id', $marketplaceId))
            ->selectRaw('op.product_id, p.sku, p.name, SUM(op.qty) as units, SUM(op.revenue) as revenue, SUM(op.cogs) as cogs, SUM(op.fees) as fees, SUM(op.return_loss) as return_loss, SUM(op.recovery) as recovery, SUM(op.ads_cost) as ads_cost, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit, SUM(CASE WHEN op.actual_profit IS NOT NULL THEN 1 ELSE 0 END) as settled_lines, COUNT(*) as line_count')
            ->groupBy('op.product_id', 'p.sku', 'p.name');

        $rows = (clone $query)->get()->map(function ($row) {
            $row->margin = (float) $row->revenue > 0 ? ((float) $row->profit / (float) $row->revenue) * 100 : null;
            $row->settled_pct = (int) $row->line_count > 0 ? ((int) $row->settled_lines / (int) $row->line_count) * 100 : 0;

            return $row;
        });
        $rows = $rows->sortBy(function ($row) use ($sort) {
            return $sort === 'margin' ? ($row->margin ?? -INF) : $row->{$sort};
        }, SORT_REGULAR, $direction === 'desc')->values();

        $summary = [
            'products' => $rows->count(),
            'units' => (int) $rows->sum('units'),
            'revenue' => (float) $rows->sum('revenue'),
            'profit' => (float) $rows->sum('profit'),
            'loss_products' => $rows->filter(fn ($r) => (float) $r->profit < 0)->count(),
        ];
        $marketplaces = Schema::hasTable('marketplaces') ? DB::table('marketplaces')->orderBy('name')->get(['id', 'name']) : collect();

        return view('product-performance.index', compact('rows', 'summary', 'from', 'to', 'marketplaces', 'marketplaceId', 'sort', 'direction'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(Schema::hasTable('order_profits') && Schema::hasTable('products'), 503);
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        validator(compact('from', 'to'), ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
        $rows = DB::table('order_profits as op')->join('products as p', 'p.id', '=', 'op.product_id')->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereBetween('op.order_date', [$from, $to])->where(function ($q) {
                $q->whereNull('o.source')->orWhere('o.source', '<>', 'TEST');
            })->whereNull('o.deleted_at')
            ->when($request->integer('marketplace_id'), fn ($q, $id) => $q->where('op.marketplace_id', $id))
            ->selectRaw('p.sku, p.name, SUM(op.qty) units, SUM(op.revenue) revenue, SUM(op.cogs) cogs, SUM(op.fees) fees, SUM(COALESCE(op.actual_profit,op.estimated_profit)) profit')
            ->groupBy('p.id', 'p.sku', 'p.name')->orderByDesc('profit')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU', 'Product', 'Units', 'Revenue', 'COGS', 'Fees', 'Profit']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->sku, $r->name, $r->units, $r->revenue, $r->cogs, $r->fees, $r->profit]);
            }
            fclose($out);
        }, 'product-performance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
