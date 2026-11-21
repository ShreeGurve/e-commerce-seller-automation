<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceOperationsIntelligenceController extends Controller
{
    private function dates(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]) + [
            'from' => $request->query('from', now()->subDays(29)->toDateString()),
            'to' => $request->query('to', now()->toDateString()),
        ];
    }

    /** Package 39: consolidated financial risk queue; read-only. */
    public function risk(Request $request)
    {
        $f = $this->dates($request);
        $issues = DB::table('reconciliation_issues as ri')
            ->leftJoin('orders as o', 'o.id', '=', 'ri.order_id')
            ->where('ri.status', 'OPEN')
            ->whereDate('ri.created_at', '<=', $f['to'])
            ->whereDate('ri.created_at', '>=', $f['from'])
            ->where(function ($q) {
                $q->whereNull('o.source')->orWhere('o.source', '!=', 'TEST');
            })
            ->select('ri.id', 'ri.issue_type', 'ri.message', 'ri.expected_amount', 'ri.actual_amount', 'ri.difference', 'ri.created_at', 'ri.external_order_id')
            ->orderByDesc('ri.created_at')->limit(250)->get();

        $claims = DB::table('claims')->whereNull('deleted_at')->whereIn('status', ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'PARTIALLY_APPROVED', 'APPEAL'])
            ->whereNotNull('deadline')->whereDate('deadline', '<=', now()->toDateString())->count();
        $unlinked = DB::table('settlement_items as si')->join('settlements as s', 's.id', '=', 'si.settlement_id')
            ->whereBetween('s.period_end', [$f['from'], $f['to']])->whereNull('si.order_id')->count();
        $summary = [
            'open_issues' => $issues->count(),
            'issue_exposure' => (float) $issues->sum(fn ($r) => abs((float) ($r->difference ?? 0))),
            'overdue_claims' => $claims,
            'unlinked_settlement_lines' => $unlinked,
        ];

        return view('finance.operations.risk', compact('f', 'issues', 'summary'));
    }

    /** Package 40: identify profit leakage signals without changing calculated values. */
    public function leakage(Request $request)
    {
        $f = $this->dates($request);
        $rows = DB::table('order_profits as op')->join('orders as o', 'o.id', '=', 'op.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'op.product_id')
            ->whereBetween('op.order_date', [$f['from'], $f['to']])->where('o.source', '!=', 'TEST')->where('o.status', '!=', 'CANCELLED')
            ->selectRaw('op.product_id, COALESCE(p.sku, CONCAT("#", op.product_id)) sku, COALESCE(p.name, "Deleted product") product_name, COUNT(*) line_count, SUM(op.revenue) revenue, SUM(op.cogs) cogs, SUM(op.fees) fees, SUM(op.return_loss) refunds, SUM(op.ads_cost) ads, SUM(COALESCE(op.actual_profit, op.estimated_profit)) profit, SUM(CASE WHEN op.actual_profit IS NOT NULL THEN 1 ELSE 0 END) actual_lines')
            ->groupBy('op.product_id', 'p.sku', 'p.name')->havingRaw('SUM(op.revenue) > 0')
            ->orderByRaw('SUM(COALESCE(op.actual_profit, op.estimated_profit)) ASC')->limit(300)->get();
        $signals = $rows->map(function ($r) {
            $r->margin_pct = round(((float) $r->profit / (float) $r->revenue) * 100, 2);
            $r->fee_pct = round(((float) $r->fees / (float) $r->revenue) * 100, 2);
            $r->refund_pct = round(((float) $r->refunds / (float) $r->revenue) * 100, 2);
            $flags = [];
            if ((float) $r->profit < 0) {
                $flags[] = 'Negative contribution';
            }
            if ($r->margin_pct >= 0 && $r->margin_pct < 10) {
                $flags[] = 'Margin below 10%';
            }
            if ($r->fee_pct > 20) {
                $flags[] = 'Fees above 20% of revenue';
            }
            if ($r->refund_pct > 15) {
                $flags[] = 'Refunds above 15% of revenue';
            }
            if ((float) $r->ads > 0 && (float) $r->profit < (float) $r->ads) {
                $flags[] = 'Profit below allocated ad spend';
            }
            $r->signals = $flags;

            return $r;
        })->filter(fn ($r) => count($r->signals) > 0)->values();
        $summary = [
            'flagged_products' => $signals->count(),
            'negative_products' => $signals->filter(fn ($r) => (float) $r->profit < 0)->count(),
            'low_margin_products' => $signals->filter(fn ($r) => $r->margin_pct >= 0 && $r->margin_pct < 10)->count(),
            'refund_exposure' => (float) $signals->sum('refunds'),
        ];

        return view('finance.operations.leakage', compact('f', 'signals', 'summary'));
    }

    /** Package 41: settlement-level quality and coverage; read-only. */
    public function settlements(Request $request)
    {
        $f = $this->dates($request);
        $rows = DB::table('settlements as s')->join('marketplaces as m', 'm.id', '=', 's.marketplace_id')
            ->leftJoin('settlement_items as si', 'si.settlement_id', '=', 's.id')
            ->where(function ($q) use ($f) {
                $q->whereBetween('s.period_end', [$f['from'], $f['to']])->orWhereBetween('s.period_start', [$f['from'], $f['to']]);
            })
            ->groupBy('s.id', 'm.name', 's.external_settlement_id', 's.period_start', 's.period_end', 's.total_amount', 's.item_count', 's.created_at')
            ->selectRaw('s.id, m.name marketplace, s.external_settlement_id, s.period_start, s.period_end, s.total_amount, s.item_count, s.created_at, COUNT(si.id) imported_lines, SUM(CASE WHEN si.order_id IS NULL THEN 1 ELSE 0 END) unlinked_lines, SUM(CASE WHEN si.kind = "SALE" THEN si.amount ELSE 0 END) sales, SUM(CASE WHEN si.kind = "REFUND" THEN si.amount ELSE 0 END) refunds, SUM(CASE WHEN si.kind IN ("REFERRAL_FEE","CLOSING_FEE","SHIPPING_FEE","COLLECTION_FEE","PAYMENT_FEE","OTHER_FEE","FEE_TAX") THEN si.amount ELSE 0 END) fees')
            ->orderByDesc('s.period_end')->limit(300)->get();
        $summary = [
            'settlements' => $rows->count(),
            'net_total' => (float) $rows->sum('total_amount'),
            'unlinked_lines' => (int) $rows->sum('unlinked_lines'),
            'line_count_variances' => $rows->filter(fn ($r) => (int) $r->item_count !== (int) $r->imported_lines)->count(),
        ];

        return view('finance.operations.settlements', compact('f', 'rows', 'summary'));
    }
}
