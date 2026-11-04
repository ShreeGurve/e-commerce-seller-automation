<?php
namespace App\Http\Controllers;

use App\Models\{Category, Marketplace, Order, OrderProfit, Product};
use App\Services\ProfitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitController extends Controller
{
    public function index(Request $request, ProfitService $profit)
    {
        $f = [
            'from' => $request->query('from', now()->subDays(29)->toDateString()), 'to' => $request->query('to', now()->toDateString()),
            'marketplace_id' => $request->query('marketplace_id'), 'category_id' => $request->query('category_id'), 'sort' => $request->query('sort', 'profit_desc'),
        ];
        $r = $profit->report($f);
        $rows = $r['byProduct'];
        $lossProducts = $rows->filter(fn ($p) => (float) $p->profit < 0)->count();
        $thinMarginProducts = $rows->filter(fn ($p) => (float) $p->revenue > 0 && ((float) $p->profit / (float) $p->revenue) < 0.10 && (float) $p->profit >= 0)->count();
        $highReturnProducts = $rows->filter(fn ($p) => (float) $p->revenue > 0 && (float) $p->refunded / (float) $p->revenue > 0.15)->count();
        $topProduct = $rows->sortByDesc(fn ($p) => (float) $p->profit)->first();
        $r['intelligence'] = compact('lossProducts', 'thinMarginProducts', 'highReturnProducts', 'topProduct');
        return view('profit.index', $r + ['f' => $f, 'marketplaces' => Marketplace::active()->get()->keyBy('id'), 'categories' => Category::orderBy('name')->get(),
            'sorts' => array_keys(ProfitService::SORTS)]);
    }

    public function export(Request $request)
    {
        $f = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'marketplace_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'],
        ]);
        $query = DB::table('order_profits as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
            ->whereBetween('op.order_date', [$f['from'], $f['to']])
            ->where('o.source', '!=', 'TEST')->where('o.status', '!=', 'CANCELLED')
            ->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('op.marketplace_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('p.category_id', $v))
            ->selectRaw('op.product_id, COALESCE(p.sku, CONCAT("#", op.product_id)) as sku, COALESCE(p.name, "Deleted product") as product_name, SUM(op.qty) as units, SUM(op.revenue) as revenue, SUM(op.cogs) as cogs, SUM(op.fees) as fees, SUM(op.return_loss) as refunds, SUM(op.ads_cost) as ads, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit')
            ->groupBy('op.product_id', 'p.sku', 'p.name')->orderBy('profit', 'desc');
        $filename = 'product-profitability-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product ID', 'SKU', 'Product', 'Units', 'Revenue', 'COGS', 'Marketplace fees', 'Refunds', 'Ads cost', 'Profit', 'Margin %']);
            foreach ($query->cursor() as $row) {
                $margin = (float) $row->revenue > 0 ? round(((float) $row->profit / (float) $row->revenue) * 100, 2) : 0;
                fputcsv($out, [$row->product_id, $row->sku, $row->product_name, $row->units, $row->revenue, $row->cogs, $row->fees, $row->refunds, $row->ads, $row->profit, $margin]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function recalculate(ProfitService $profit)
    {
        if (Order::count() > 5000) return back()->with('error', 'Many orders: run  php artisan profit:recalculate  in the project folder instead.');
        set_time_limit(300);
        return back()->with('success', 'Profit recalculated for ' . number_format($profit->recalcAll()) . ' orders.');
    }
}
