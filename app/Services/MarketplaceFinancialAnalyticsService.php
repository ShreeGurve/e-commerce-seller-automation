<?php

namespace App\Services;

use App\Models\Marketplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceFinancialAnalyticsService
{
    /**
     * Read-only financial dashboard. Profit figures use the existing OrderProfit
     * calculation and retain the distinction between estimated and actual rows.
     */
    public function dashboard(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => 'The start date must be before or equal to the end date.']);
        }
        $marketplaceId = $filters['marketplace_id'] ?? null;

        $profitBase = DB::table('order_profits as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereBetween('op.order_date', [$from, $to])
            ->where(function ($q) { $q->whereNull('o.source')->orWhere('o.source', '!=', 'TEST'); })
            ->where('o.status', '!=', 'CANCELLED')
            ->when($marketplaceId, fn ($q) => $q->where('op.marketplace_id', $marketplaceId));

        $profit = (clone $profitBase)->selectRaw('COUNT(DISTINCT op.order_id) as orders, SUM(op.qty) as units, SUM(op.revenue) as revenue, SUM(op.cogs) as cogs, SUM(op.fees) as fees, SUM(op.return_loss) as return_loss, SUM(op.recovery) as recovery, SUM(op.ads_cost) as ads_cost, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as net_profit, SUM(CASE WHEN op.actual_profit IS NOT NULL THEN 1 ELSE 0 END) as actual_lines, COUNT(*) as total_lines')->first();

        $settlements = DB::table('settlements')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('period_start', [$from, $to])->orWhereBetween('period_end', [$from, $to])
                    ->orWhere(function ($q) use ($from, $to) { $q->where('period_start', '<=', $from)->where('period_end', '>=', $to); });
            })
            ->when($marketplaceId, fn ($q) => $q->where('marketplace_id', $marketplaceId));
        $settlementSummary = (clone $settlements)->selectRaw('COUNT(*) as count, SUM(total_amount) as net_settlement')->first();

        $payouts = DB::table('marketplace_bank_payouts')
            ->whereBetween('payout_date', [$from, $to])
            ->when($marketplaceId, fn ($q) => $q->where('marketplace_id', $marketplaceId));
        $payoutSummary = (clone $payouts)->selectRaw("COUNT(*) as count, SUM(bank_amount) as bank_credits, SUM(CASE WHEN status = 'MATCHED' THEN bank_amount ELSE 0 END) as matched_credits, SUM(CASE WHEN status IN ('MISMATCH','UNMATCHED') THEN 1 ELSE 0 END) as attention_count")->first();

        $settlementLines = DB::table('settlement_items as si')
            ->join('settlements as s', 's.id', '=', 'si.settlement_id')
            ->whereBetween('si.posted_date', [$from, $to])
            ->when($marketplaceId, fn ($q) => $q->where('s.marketplace_id', $marketplaceId));
        $feeBreakdown = (clone $settlementLines)->whereIn('si.kind', ['REFERRAL_FEE','CLOSING_FEE','SHIPPING_FEE','COLLECTION_FEE','PAYMENT_FEE','OTHER_FEE','FEE_TAX'])
            ->selectRaw('si.kind, SUM(ABS(si.amount)) as amount, COUNT(*) as lines')->groupBy('si.kind')->orderBy('si.kind')->get();

        $refunds = DB::table('refunds')->whereNull('deleted_at')->whereBetween('refund_date', [$from, $to])
            ->when($marketplaceId, fn ($q) => $q->whereIn('order_id', DB::table('orders')->select('id')->where('marketplace_id', $marketplaceId)))
            ->sum('amount');
        $claims = DB::table('claims')->whereNull('deleted_at')->whereBetween('decision_date', [$from, $to])
            ->whereIn('status', ['APPROVED', 'PARTIALLY_APPROVED', 'CLOSED'])
            ->when($marketplaceId, fn ($q) => $q->where('marketplace_id', $marketplaceId))->sum('approved_amount');

        $expenseQuery = DB::table('expenses')->whereNull('deleted_at')->whereBetween('expense_date', [$from, $to])
            ->when($marketplaceId, fn ($q) => $q->where(function ($q) use ($marketplaceId) { $q->whereNull('marketplace_id')->orWhere('marketplace_id', $marketplaceId); }));
        $expenses = (float) $expenseQuery->sum('amount');

        $byMarketplace = (clone $profitBase)->selectRaw('op.marketplace_id, COUNT(DISTINCT op.order_id) as orders, SUM(op.revenue) as revenue, SUM(COALESCE(op.actual_profit, op.estimated_profit)) as net_profit')
            ->groupBy('op.marketplace_id')->orderByDesc('net_profit')->get();
        $marketplaceNames = Marketplace::whereIn('id', $byMarketplace->pluck('marketplace_id'))->pluck('name', 'id');

        return [
            'filters' => ['from' => $from, 'to' => $to, 'marketplace_id' => $marketplaceId],
            'totals' => $profit,
            'actual_coverage' => (int) ($profit->total_lines ?? 0) > 0 ? round(((int) $profit->actual_lines / (int) $profit->total_lines) * 100, 1) : 0,
            'settlements' => $settlementSummary,
            'payouts' => $payoutSummary,
            'fee_breakdown' => $feeBreakdown,
            'refund_total' => (float) $refunds,
            'claim_recovery' => (float) $claims,
            'expenses' => $expenses,
            'estimated_operating_profit' => round((float) ($profit->net_profit ?? 0) - $expenses, 2),
            'by_marketplace' => $byMarketplace,
            'marketplace_names' => $marketplaceNames,
        ];
    }
}
