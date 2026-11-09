<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialReconciliationController extends Controller
{
    /** Package 33: refund register and refund-to-order exception review. */
    public function refunds(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rows = DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('returns as ret', 'ret.id', '=', 'r.return_id')
            ->where('o.source', '<>', 'TEST')->whereNull('o.deleted_at')->whereNull('r.deleted_at')
            ->whereBetween('r.refund_date', [$from, $to])
            ->select('r.id', 'r.refund_date', 'r.amount', 'r.external_refund_id', 'r.reason', 'o.order_number', 'o.external_order_id', 'o.total_amount', 'ret.return_number')
            ->orderByDesc('r.refund_date')->paginate(30)->withQueryString();
        $base = DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('o.source', '<>', 'TEST')->whereNull('o.deleted_at')->whereNull('r.deleted_at')->whereBetween('r.refund_date', [$from, $to]);
        $summary = ['count' => (clone $base)->count(), 'amount' => (clone $base)->sum('r.amount')];
        $overOrder = DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('o.source', '<>', 'TEST')->whereNull('o.deleted_at')->whereNull('r.deleted_at')
            ->whereBetween('r.refund_date', [$from, $to])->groupBy('r.order_id')
            ->havingRaw('SUM(r.amount) > MAX(o.total_amount)')->get()->count();
        return view('operations.refund-reconciliation', compact('rows', 'summary', 'overOrder', 'from', 'to'));
    }

    /** Package 34: claim recovery and unresolved exposure review (not a deadline monitor). */
    public function claimRecovery(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $query = DB::table('claims as c')->leftJoin('orders as o', 'o.id', '=', 'c.order_id')
            ->leftJoin('marketplaces as m', 'm.id', '=', 'c.marketplace_id')->whereNull('c.deleted_at')
            ->where(function ($q) { $q->whereNull('o.source')->orWhere('o.source', '<>', 'TEST'); })
            ->whereBetween(DB::raw('COALESCE(c.submitted_at, DATE(c.created_at))'), [$from, $to]);
        $rows = (clone $query)->select('c.id', 'c.claim_number', 'c.claim_type', 'c.status', 'c.claim_amount', 'c.approved_amount', 'c.rejected_amount', 'c.submitted_at', 'c.decision_date', 'm.name as marketplace_name', 'o.order_number')
            ->orderByDesc('c.created_at')->paginate(30)->withQueryString();
        $summary = (clone $query)->selectRaw('COUNT(*) as claim_count, COALESCE(SUM(c.claim_amount),0) as claimed, COALESCE(SUM(c.approved_amount),0) as approved, COALESCE(SUM(c.rejected_amount),0) as rejected')->first();
        $openStatuses = ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPEAL'];
        $openExposure = (clone $query)->whereIn('c.status', $openStatuses)->sum(DB::raw('GREATEST(c.claim_amount - c.approved_amount - c.rejected_amount, 0)'));
        return view('operations.claim-recovery', compact('rows', 'summary', 'openExposure', 'from', 'to'));
    }

    /** Package 35: settlement coverage gaps and imported lines not linked to an order. */
    public function settlementCoverage(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $eligible = ['SHIPPED', 'DELIVERED', 'RETURNED', 'REFUNDED', 'CLOSED'];
        $missing = DB::table('orders as o')->leftJoin('settlement_items as si', 'si.order_id', '=', 'o.id')
            ->where('o.source', '<>', 'TEST')->whereNull('o.deleted_at')->whereIn('o.status', $eligible)
            ->whereBetween('o.order_date', [$from.' 00:00:00', $to.' 23:59:59'])->groupBy('o.id', 'o.order_number', 'o.external_order_id', 'o.order_date', 'o.status', 'o.total_amount')
            ->havingRaw('COUNT(si.id) = 0')->select('o.id', 'o.order_number', 'o.external_order_id', 'o.order_date', 'o.status', 'o.total_amount')->orderBy('o.order_date')->paginate(30)->withQueryString();
        $unlinked = DB::table('settlement_items as si')->join('settlements as s', 's.id', '=', 'si.settlement_id')
            ->whereNull('si.order_id')->whereNotNull('si.external_order_id')->whereBetween('s.period_start', [$from, $to])
            ->select('si.id', 'si.external_order_id', 'si.sku', 'si.kind', 'si.amount', 'si.posted_date', 's.external_settlement_id', 's.marketplace_id')
            ->orderByDesc('si.posted_date')->limit(100)->get();
        $summary = [
            'eligible' => DB::table('orders')->where('source', '<>', 'TEST')->whereNull('deleted_at')->whereIn('status', $eligible)->whereBetween('order_date', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'missing' => DB::table('orders as o')->where('o.source', '<>', 'TEST')->whereNull('o.deleted_at')->whereIn('o.status', $eligible)->whereBetween('o.order_date', [$from.' 00:00:00', $to.' 23:59:59'])->whereNotExists(function ($q) { $q->selectRaw('1')->from('settlement_items as si')->whereColumn('si.order_id', 'o.id'); })->count(),
            'unlinked' => DB::table('settlement_items as si')->join('settlements as s', 's.id', '=', 'si.settlement_id')->whereNull('si.order_id')->whereNotNull('si.external_order_id')->whereBetween('s.period_start', [$from, $to])->count(),
        ];
        return view('operations.settlement-coverage', compact('missing', 'unlinked', 'summary', 'from', 'to'));
    }

    private function dateRange(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $to = $data['to'] ?? now()->toDateString();
        $from = $data['from'] ?? now()->subDays(30)->toDateString();
        return [$from, $to];
    }
}
