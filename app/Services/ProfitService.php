<?php
namespace App\Services;

use App\Models\{Claim, Expense, Order, OrderItemFee, OrderProfit, Product, ReturnItem, SettlementItem};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Net profit per order line.
 *   revenue (item ex-GST + shipping charged) - COGS (cost ON ORDER DATE, minus units restocked)
 *   - marketplace fees (+GST on fees) - refunds (ex-GST)  [+ claim recovery - ads: later phases]
 * Estimated profit is stored when the order is created; actual profit is added once settlements arrive (Phase 4b).
 */
class ProfitService
{
    public function __construct(private FeeService $fees, private AdAllocationService $ads, private SummaryService $summary) {}

    /** never let the speed tables break profit calculation (e.g. before the migration has run) */
    private function touchSummary(Order $o): void
    {
        try { $this->summary->touch($o->order_date->toDateString(), (int) $o->marketplace_id); } catch (\Throwable $e) { report($e); }
    }

    /** Recalculate, but never let a profit problem break the order/return/refund that triggered it. */
    public function safe(Order $order): void
    {
        try { $this->recalcOrder($order); } catch (\Throwable $e) { report($e); }
    }

    private const FEE_TYPE = ['REFERRAL_FEE' => 'REFERRAL', 'CLOSING_FEE' => 'CLOSING', 'SHIPPING_FEE' => 'SHIPPING', 'COLLECTION_FEE' => 'COLLECTION', 'PAYMENT_FEE' => 'PAYMENT', 'OTHER_FEE' => 'OTHER'];

    public function recalcOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $o = Order::with(['items.product', 'refunds'])->find($order->id);
            if (! $o) return;
            $ids = $o->items->pluck('id');

            if ($o->status === 'CANCELLED') {            // nothing sold, nothing earned
                OrderItemFee::whereIn('order_item_id', $ids)->delete();
                OrderProfit::whereIn('order_item_id', $ids)->delete();
                foreach ($o->items->pluck('product_id')->unique() as $pid) $this->ads->allocateKey($o->marketplace_id, $pid, $o->order_date->toDateString());
                $this->touchSummary($o);
                return;
            }
            $subtotal = Money::p($o->subtotal);
            $ship = Money::p($o->shipping_charged);
            $refunds = Money::p($o->refunds->sum('amount'));
            $restocked = ReturnItem::whereIn('order_item_id', $ids)->selectRaw('order_item_id, SUM(restocked_qty) q')->groupBy('order_item_id')->pluck('q', 'order_item_id');
            $feeGstIsCost = (float) SettingsService::get('fee_gst_rate', 18) > 0;

            // Settlement lines (actual money from the marketplace): per line item, or per order when the SKU was not given.
            $settle = SettlementItem::where('order_id', $o->id)->get(['order_item_id', 'kind', 'amount']);
            $itemAmt = []; $orderAmt = [];
            foreach ($settle as $sl) {
                $paise = Money::p($sl->amount);
                if ($sl->order_item_id && $ids->contains($sl->order_item_id)) $itemAmt[$sl->order_item_id][$sl->kind] = ($itemAmt[$sl->order_item_id][$sl->kind] ?? 0) + $paise;
                else $orderAmt[$sl->kind] = ($orderAmt[$sl->kind] ?? 0) + $paise;
            }
            $settled = $settle->contains(fn ($x) => $x->kind === 'SALE' || in_array($x->kind, SettlementItem::FEE_KINDS, true));
            $hasRefundLines = $settle->contains('kind', 'REFUND');
            $hasReimbursement = $settle->contains('kind', 'REIMBURSEMENT');

            // Approved claim money counts as recovery until the real payout appears in a settlement (then the payout line replaces it: never counted twice).
            $claimRec = [];
            foreach (Claim::where('order_id', $o->id)->where('approved_amount', '>', 0)->get(['product_id', 'approved_amount']) as $c) {
                $elig = $o->items->filter(fn ($i) => ! $c->product_id || $i->product_id === $c->product_id)->values();
                if ($elig->isEmpty()) $elig = $o->items->values();
                $tot = max($elig->sum(fn ($i) => Money::p($i->line_total)), 1); $amt = Money::p($c->approved_amount); $left = $amt; $last = $elig->count() - 1;
                foreach ($elig as $k => $i) {
                    $share = $k === $last ? $left : (int) round($amt * Money::p($i->line_total) / $tot);
                    $left -= $share; $claimRec[$i->id] = ($claimRec[$i->id] ?? 0) + $share;
                }
            }

            foreach ($o->items as $item) {
                $L = Money::p($item->line_total); $T = Money::p($item->tax_amount); $qty = (int) $item->quantity;
                $revenue = $L - $T + ($subtotal > 0 ? (int) round($ship * $L / $subtotal) : 0);
                $unit = Money::p($item->cogs_at_sale) + Money::p($item->packaging_at_sale) + Money::p($item->other_cost_at_sale);
                $cogs = $unit * max($qty - (int) ($restocked[$item->id] ?? 0), 0);          // sellable returns put the cost back
                $refundEst = $subtotal > 0 ? (int) round($refunds * ($L - $T) / $subtotal) : 0;   // manual refund share, ex-GST

                OrderItemFee::where('order_item_id', $item->id)->delete();
                $feeEst = 0;
                foreach ($this->fees->estimate($item->product, $o->marketplace_id, $L, $T, $qty, $o->order_date) as $f) {
                    OrderItemFee::create(['order_item_id' => $item->id, 'fee_type' => $f['type'], 'amount' => Money::m($f['amount']), 'tax_amount' => Money::m($f['tax']),
                        'source' => 'ESTIMATED', 'rule_id' => $f['rule_id']]);
                    $feeEst += $f['amount'] + $f['tax'];
                }
                $ads = Money::p(OrderProfit::where('order_item_id', $item->id)->value('ads_cost') ?? 0);   // set by the advertising phase
                $cr = $claimRec[$item->id] ?? 0;
                $estimated = $revenue - $cogs - $feeEst - $refundEst - $ads + $cr;

                $row = ['order_id' => $o->id, 'product_id' => $item->product_id, 'marketplace_id' => $o->marketplace_id, 'order_date' => $o->order_date->toDateString(),
                    'qty' => $qty, 'revenue' => Money::m($revenue), 'cogs' => Money::m($cogs), 'ads_cost' => Money::m($ads), 'estimated_profit' => Money::m($estimated), 'calculated_at' => now()];

                if ($settled) {
                    // share of order-level lines that carry no SKU, split by line value
                    $alloc = fn (string $k) => ($itemAmt[$item->id][$k] ?? 0) + ($subtotal > 0 ? (int) round(($orderAmt[$k] ?? 0) * $L / $subtotal) : 0);
                    $feeAct = 0;
                    foreach (self::FEE_TYPE as $kind => $type) {
                        $a = -$alloc($kind);
                        if ($a !== 0) { OrderItemFee::create(['order_item_id' => $item->id, 'fee_type' => $type, 'amount' => Money::m($a), 'tax_amount' => '0.00', 'source' => 'SETTLEMENT']); $feeAct += $a; }
                    }
                    $gst = -$alloc('FEE_TAX');
                    if ($gst !== 0) { OrderItemFee::create(['order_item_id' => $item->id, 'fee_type' => 'GST_ON_FEES', 'amount' => Money::m($gst), 'tax_amount' => '0.00', 'source' => 'SETTLEMENT']); if ($feeGstIsCost) $feeAct += $gst; }
                    $refundAct = $hasRefundLines ? (int) round(-$alloc('REFUND') * ($L > 0 ? ($L - $T) / $L : 1)) : $refundEst;   // principal assumed GST-inclusive like order prices
                    $recovery = $alloc('REIMBURSEMENT') + $alloc('ADJUSTMENT') + $alloc('OTHER');
                    if (! $hasReimbursement) $recovery += $cr;
                    $row += ['fees' => Money::m($feeAct), 'return_loss' => Money::m($refundAct), 'recovery' => Money::m($recovery),
                        'actual_profit' => Money::m($revenue - $cogs - $feeAct - $refundAct + $recovery - $ads), 'profit_status' => 'ACTUAL'];
                } else {
                    $row += ['fees' => Money::m($feeEst), 'return_loss' => Money::m($refundEst), 'recovery' => Money::m($cr), 'actual_profit' => null, 'profit_status' => 'ESTIMATED'];
                }
                OrderProfit::updateOrCreate(['order_item_id' => $item->id], $row);
            }
            foreach ($o->items->pluck('product_id')->unique() as $pid) $this->ads->allocateKey($o->marketplace_id, $pid, $o->order_date->toDateString());   // ad spend for this product/day is shared across its lines
            $this->touchSummary($o);
        });
    }

    public function recalcAll(): int
    {
        $n = 0; $this->summary->begin();                      // refresh each affected day once at the end, not once per order
        try {
            Order::where('status', '!=', 'CANCELLED')->select('id')->chunkById(200, function ($chunk) use (&$n) {
                foreach ($chunk as $o) { $this->recalcOrder($o); $n++; }
            });
        } finally {
            try { $this->summary->flush(); } catch (\Throwable $e) { report($e); }
        }
        return $n;
    }

    public const SORTS = ['profit_desc' => 'profit DESC', 'profit_asc' => 'profit ASC', 'revenue_desc' => 'revenue DESC', 'returns_desc' => 'refunded DESC', 'units_desc' => 'units DESC'];

    /** @param array{from:string,to:string,marketplace_id?:?int,product_id?:?int,category_id?:?int,sort?:string} $f */
    public function report(array $f): array
    {
        $base = OrderProfit::query()->whereBetween('order_date', [$f['from'], $f['to']])
            ->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->whereIn('product_id', Product::where('category_id', $v)->select('id')));
        $cols = 'COUNT(DISTINCT order_id) orders, SUM(qty) units, SUM(revenue) revenue, SUM(cogs) cogs, SUM(fees) fees, SUM(return_loss) refunded, SUM(recovery) recovery, SUM(ads_cost) ads, SUM(COALESCE(actual_profit, estimated_profit)) profit';

        $t = (clone $base)->selectRaw($cols . ', SUM(CASE WHEN actual_profit IS NOT NULL THEN 1 ELSE 0 END) actual_rows, COUNT(*) all_rows')->first();
        $sort = self::SORTS[$f['sort'] ?? ''] ?? self::SORTS['profit_desc'];
        $byProduct = (clone $base)->selectRaw("product_id, $cols")->groupBy('product_id')->orderByRaw($sort)->limit(300)->get();
        $names = Product::withTrashed()->whereIn('id', $byProduct->pluck('product_id'))->get(['id', 'sku', 'name'])->keyBy('id');
        $byMp = (clone $base)->selectRaw("marketplace_id, $cols")->groupBy('marketplace_id')->orderByRaw('profit DESC')->get();

        $exp = Expense::whereBetween('expense_date', [$f['from'], $f['to']])->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('marketplace_id', $v));
        // ad spend that no sale could carry (campaign-only reports, or days with ad clicks but no orders) still costs money
        $adsTotal = app(AdsService::class)->totalSpend($f['from'], $f['to'], $f['marketplace_id'] ?? null);
        $unalloc = (empty($f['product_id']) && empty($f['category_id'])) ? max($adsTotal - (float) ($t->ads ?? 0), 0) : 0;
        return ['totals' => $t, 'byProduct' => $byProduct, 'names' => $names, 'byMarketplace' => $byMp, 'expenses' => (float) $exp->sum('amount'),
            'adsTotal' => round($adsTotal, 2), 'adsUnallocated' => round($unalloc, 2)];
    }
}
