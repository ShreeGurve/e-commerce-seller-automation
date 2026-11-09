<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceOperationsInsightController extends Controller
{
    public function profitabilityExceptions(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $threshold = min(50, max(0, (float) $request->query('margin', 10)));

        $rows = DB::table('order_profits as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
            ->leftJoin('marketplaces as m', 'm.id', '=', 'op.marketplace_id')
            ->whereNull('o.deleted_at')->where('o.source', '<>', 'TEST')->where('o.status', '<>', 'CANCELLED')
            ->whereBetween('op.order_date', [$from, $to])
            ->where(function ($q) use ($threshold) {
                $q->whereRaw('COALESCE(op.actual_profit, op.estimated_profit) < 0')
                  ->orWhereRaw('(op.revenue > 0 AND (COALESCE(op.actual_profit, op.estimated_profit) / op.revenue) * 100 < ?)', [$threshold]);
            })
            ->orderByRaw('COALESCE(op.actual_profit, op.estimated_profit) ASC')
            ->limit(500)
            ->get(['op.order_id', 'o.order_number', 'op.order_date', 'p.sku', 'p.name as product_name', 'm.name as marketplace', 'op.qty', 'op.revenue', 'op.cogs', 'op.fees', 'op.return_loss', 'op.estimated_profit', 'op.actual_profit']);

        $summary = (object) [
            'lines' => $rows->count(),
            'losses' => $rows->filter(fn ($r) => (float) ($r->actual_profit ?? $r->estimated_profit) < 0)->count(),
            'exposure' => abs((float) $rows->sum(fn ($r) => min(0, (float) ($r->actual_profit ?? $r->estimated_profit)))),
        ];
        return view('finance.operations.profitability-exceptions', compact('from', 'to', 'threshold', 'rows', 'summary'));
    }

    public function refundImpact(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $refunds = DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('marketplaces as m', 'm.id', '=', 'o.marketplace_id')
            ->whereNull('r.deleted_at')->whereNull('o.deleted_at')->where('o.source', '<>', 'TEST')
            ->whereBetween('r.refund_date', [$from, $to]);

        $total = (clone $refunds)->selectRaw('COUNT(*) as refund_count, COALESCE(SUM(r.amount),0) as refund_amount, COUNT(DISTINCT r.order_id) as affected_orders')->first();
        $byReason = (clone $refunds)->selectRaw("COALESCE(NULLIF(r.reason, ''), 'Unspecified') as reason, COUNT(*) as refund_count, SUM(r.amount) as amount")
            ->groupBy('reason')->orderByDesc('amount')->get();
        $recent = (clone $refunds)->orderByDesc('r.refund_date')->orderByDesc('r.id')->limit(200)
            ->get(['r.refund_date', 'r.amount', 'r.reason', 'o.order_number', 'm.name as marketplace']);

        return view('finance.operations.refund-impact', compact('from', 'to', 'total', 'byReason', 'recent'));
    }

    public function marketplacePerformance(Request $request)
    {
        [$from, $to] = $this->dates($request);
        $performance = DB::table('order_profits as op')->join('orders as o', 'o.id', '=', 'op.order_id')
            ->join('marketplaces as m', 'm.id', '=', 'op.marketplace_id')
            ->whereNull('o.deleted_at')->where('o.source', '<>', 'TEST')->where('o.status', '<>', 'CANCELLED')
            ->whereBetween('op.order_date', [$from, $to])
            ->groupBy('m.id', 'm.name')
            ->selectRaw('m.id, m.name, COUNT(DISTINCT op.order_id) as orders, SUM(op.qty) as units, SUM(op.revenue) as revenue, SUM(op.cogs) as cogs, SUM(op.fees) as fees, SUM(op.return_loss) as return_loss, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as profit')
            ->orderByDesc('revenue')->get();

        $refunds = DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->whereNull('r.deleted_at')->whereNull('o.deleted_at')->where('o.source', '<>', 'TEST')
            ->whereBetween('r.refund_date', [$from, $to])->groupBy('o.marketplace_id')
            ->selectRaw('o.marketplace_id, SUM(r.amount) as refund_amount')->pluck('refund_amount', 'marketplace_id');

        $performance->transform(function ($row) use ($refunds) {
            $row->refund_amount = (float) ($refunds[$row->id] ?? 0);
            $row->margin = (float) $row->revenue > 0 ? ((float) $row->profit / (float) $row->revenue) * 100 : 0;
            return $row;
        });
        return view('finance.operations.marketplace-performance', compact('from', 'to', 'performance'));
    }

    private function dates(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'margin' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->toDateString();
        $from = Carbon::parse($validated['from'] ?? now()->subDays(29)->toDateString())->toDateString();
        return [$from, $to];
    }
}
