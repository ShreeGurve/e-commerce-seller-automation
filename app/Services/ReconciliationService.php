<?php
namespace App\Services;

use App\Models\{Claim, Order, OrderItem, ReconciliationIssue, Refund, SettlementItem};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Compares what we expected (orders, fee rules, refunds) with what the marketplace actually paid (settlement lines).
 * Issues are de-duplicated by fingerprint; fixed problems close themselves; issues you IGNORE stay ignored.
 */
class ReconciliationService
{
    private const ORDER_TYPES = ['MISSING_SETTLEMENT', 'FEE_MISMATCH', 'SALE_MISMATCH', 'REFUND_MISMATCH', 'CLAIM_UNPAID'];

    public function __construct(private ProfitService $profit) {}

    /** Settlement lines that arrived before their order: attach them now. Returns affected order ids. */
    public function relink(): array
    {
        $touched = []; $cache = [];
        SettlementItem::whereNull('order_id')->whereNotNull('external_order_id')->with('settlement:id,marketplace_id')->chunkById(500, function ($chunk) use (&$touched, &$cache) {
            foreach ($chunk as $it) {
                $key = $it->settlement->marketplace_id . '|' . $it->external_order_id;
                $cache[$key] ??= Order::where('marketplace_id', $it->settlement->marketplace_id)->where('external_order_id', $it->external_order_id)->value('id') ?: 0;
                if (! $cache[$key]) continue;
                $itemId = null;
                if ($it->sku) {
                    $pid = \App\Models\ProductMarketplace::where('marketplace_id', $it->settlement->marketplace_id)->where('marketplace_sku', $it->sku)->value('product_id')
                        ?? \App\Models\Product::where('sku', $it->sku)->value('id');
                    $itemId = $pid ? OrderItem::where('order_id', $cache[$key])->where('product_id', $pid)->value('id') : null;
                }
                $it->update(['order_id' => $cache[$key], 'order_item_id' => $itemId]);
                $touched[$cache[$key]] = true;
            }
        });
        return array_keys($touched);
    }

    /** Full run (button) or scoped run (after an import). Returns counts. */
    public function run(?array $orderIds = null, ?array $settlementIds = null): array
    {
        $relinked = $this->relink();
        foreach ($relinked as $id) { if ($o = Order::find($id)) $this->profit->safe($o); }
        $scope = $orderIds === null ? null : array_values(array_unique(array_merge($orderIds, $relinked)));

        $found = []; $scanned = [];
        $waitDays = (int) SettingsService::get('settlement_wait_days', 14);
        $tolPct = (float) SettingsService::get('recon_fee_tolerance_percent', 20);
        $gstIsCost = (float) SettingsService::get('fee_gst_rate', 18) > 0;
        $cutoff = now()->subDays($waitDays);

        Order::where('status', '!=', 'CANCELLED')->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->chunkById(200, function ($orders) use (&$found, &$scanned, $cutoff, $tolPct, $gstIsCost) {
            $ids = $orders->pluck('id')->all();
            $sum = SettlementItem::whereIn('order_id', $ids)->selectRaw('order_id, kind, SUM(amount) amt')->groupBy('order_id', 'kind')->get()->groupBy('order_id');
            $estFees = DB::table('order_item_fees as f')->join('order_items as i', 'i.id', '=', 'f.order_item_id')->whereIn('i.order_id', $ids)->where('f.source', 'ESTIMATED')
                ->selectRaw('i.order_id, SUM(f.amount + f.tax_amount) amt')->groupBy('i.order_id')->pluck('amt', 'i.order_id');
            $manualRefunds = Refund::whereIn('order_id', $ids)->selectRaw('order_id, SUM(amount) a')->groupBy('order_id')->pluck('a', 'order_id');

            foreach ($orders as $o) {
                $scanned[] = $o->id;
                $k = ($sum[$o->id] ?? collect())->pluck('amt', 'kind');
                $hasSettlement = $k->isNotEmpty();
                $sale = Money::p($k['SALE'] ?? 0);
                $actualFees = 0;
                foreach (SettlementItem::FEE_KINDS as $fk) $actualFees += -Money::p($k[$fk] ?? 0);
                if ($gstIsCost) $actualFees += -Money::p($k['FEE_TAX'] ?? 0);
                $estFee = Money::p($estFees[$o->id] ?? 0);

                if (! $hasSettlement) {
                    if (in_array($o->status, ['SHIPPED', 'DELIVERED', 'RETURNED', 'REFUNDED', 'CLOSED'], true) && $o->order_date->lt($cutoff)) {
                        $expected = Money::p($o->total_amount) - $estFee;
                        $found[] = $this->issue('MISSING_SETTLEMENT', $o, null, $expected, 0, "No payout line found {$o->order_date->diffInDays(now())} days after the order.");
                    }
                    continue;
                }
                if ($sale > 0 || $k->has('SALE')) {
                    $expected = Money::p($o->subtotal);
                    if (abs($sale - $expected) > max(100, (int) round($expected * 0.01))) {
                        $found[] = $this->issue('SALE_MISMATCH', $o, null, $expected, $sale, 'Sale amount paid differs from the order value.');
                    }
                }
                if ($estFee > 0 && $actualFees > 0 && abs($actualFees - $estFee) > max(500, (int) round($estFee * $tolPct / 100))) {
                    $found[] = $this->issue('FEE_MISMATCH', $o, null, $estFee, $actualFees, 'Fees charged differ from your fee rules.');
                }
                $paidRefund = -Money::p($k['REFUND'] ?? 0); $manual = Money::p($manualRefunds[$o->id] ?? 0);
                if (($paidRefund > 0 || $manual > 0) && abs($paidRefund - $manual) > 100) {
                    $found[] = $this->issue('REFUND_MISMATCH', $o, null, $manual, $paidRefund, 'Refund recorded in the system differs from the refund in the settlement.');
                }
            }
        });

        // approved claims whose money never showed up as a reimbursement line in a settlement
        if ($scanned) {
            $paidBy = SettlementItem::where('kind', 'REIMBURSEMENT')->whereIn('order_id', $scanned)->selectRaw('order_id, SUM(amount) a')->groupBy('order_id')->pluck('a', 'order_id');
            $approved = Claim::where('approved_amount', '>', 0)->whereNotNull('order_id')->whereIn('order_id', $scanned)->whereDate('decision_date', '<=', $cutoff->toDateString())
                ->selectRaw('order_id, SUM(approved_amount) a')->groupBy('order_id')->pluck('a', 'order_id');
            foreach ($approved as $oid => $a) {
                $ap = Money::p($a); $pd = Money::p($paidBy[$oid] ?? 0);
                if ($pd < $ap - 100 && ($o = Order::find($oid))) $found[] = $this->issue('CLAIM_UNPAID', $o, null, $ap, $pd, 'Claim approved but the reimbursement is not in any settlement yet.');
            }
        }

        // settlement lines whose order we do not have
        $unknown = SettlementItem::whereNull('order_id')->whereNotNull('external_order_id')
            ->when($settlementIds !== null, fn ($q) => $q->whereIn('settlement_id', $settlementIds))
            ->selectRaw('settlement_id, external_order_id, SUM(amount) amt')->groupBy('settlement_id', 'external_order_id')->get();
        foreach ($unknown as $u) {
            $found[] = ['type' => 'UNKNOWN_ORDER', 'order_id' => null, 'settlement_id' => $u->settlement_id, 'ext' => $u->external_order_id, 'expected' => null,
                'actual' => Money::p($u->amt), 'message' => "Settlement has lines for order {$u->external_order_id} but that order is not in the system. Import the orders.", 'fp' => "UNKNOWN_ORDER|{$u->settlement_id}|{$u->external_order_id}"];
        }

        $opened = $this->sync($found, $scanned, $settlementIds);
        $this->setOrderStatuses($scanned);
        return ['scanned' => count($scanned), 'open' => ReconciliationIssue::where('status', 'OPEN')->count(), 'new' => $opened, 'relinked' => count($relinked)];
    }

    private function issue(string $type, Order $o, ?int $sid, int $expected, int $actual, string $msg): array
    {
        return ['type' => $type, 'order_id' => $o->id, 'settlement_id' => $sid, 'ext' => $o->external_order_id, 'expected' => $expected, 'actual' => $actual, 'message' => $msg, 'fp' => "{$type}|{$o->id}"];
    }

    /** @return int number of newly opened issues */
    private function sync(array $found, array $scannedOrderIds, ?array $settlementIds): int
    {
        $new = 0; $fps = [];
        foreach ($found as $f) {
            $fps[] = $f['fp'];
            $diff = $f['expected'] === null ? null : $f['actual'] - $f['expected'];
            $vals = ['issue_type' => $f['type'], 'order_id' => $f['order_id'], 'settlement_id' => $f['settlement_id'], 'external_order_id' => $f['ext'],
                'expected_amount' => $f['expected'] === null ? null : Money::m($f['expected']), 'actual_amount' => Money::m($f['actual']),
                'difference' => $diff === null ? null : Money::m($diff), 'message' => $f['message']];
            $issue = ReconciliationIssue::where('fingerprint', $f['fp'])->first();
            if (! $issue) { ReconciliationIssue::create($vals + ['fingerprint' => $f['fp'], 'status' => 'OPEN', 'detected_at' => now()]); $new++; }
            elseif ($issue->status === 'RESOLVED') { $issue->update($vals + ['status' => 'OPEN', 'resolved_at' => null, 'resolved_by' => null]); $new++; }   // came back
            elseif ($issue->status === 'OPEN') { $issue->update($vals); }
            // IGNORED stays ignored
        }
        // anything we checked that is now fine closes itself
        ReconciliationIssue::where('status', 'OPEN')->whereNull('resolved_by')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereIn('issue_type', self::ORDER_TYPES)->whereIn('order_id', $scannedOrderIds ?: [0]))
                ->orWhere(fn ($w) => $w->where('issue_type', 'UNKNOWN_ORDER')->when($settlementIds !== null, fn ($x) => $x->whereIn('settlement_id', $settlementIds))))
            ->whereNotIn('fingerprint', $fps ?: ['-'])->update(['status' => 'RESOLVED', 'resolved_at' => now(), 'note' => 'Auto-closed: no longer detected']);
        return $new;
    }

    private function setOrderStatuses(array $ids): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            $withSettlement = SettlementItem::whereIn('order_id', $chunk)->distinct()->pluck('order_id')->all();
            $withIssue = ReconciliationIssue::where('status', 'OPEN')->whereIn('order_id', $chunk)->distinct()->pluck('order_id')->all();
            Order::whereIn('id', $chunk)->update(['recon_status' => 'PENDING']);
            if ($withSettlement) Order::whereIn('id', array_diff($withSettlement, $withIssue))->update(['recon_status' => 'MATCHED']);
            if ($withIssue) Order::whereIn('id', $withIssue)->update(['recon_status' => 'MISMATCH']);
        }
        Order::where('status', 'CANCELLED')->where('recon_status', '!=', 'NOT_APPLICABLE')->update(['recon_status' => 'NOT_APPLICABLE']);
    }
}
