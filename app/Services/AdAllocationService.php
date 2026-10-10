<?php
namespace App\Services;

use App\Models\{AdvertisingDaily, OrderProfit};
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Pushes advertised-PRODUCT spend onto the order lines of that product on that day (split by units, exact to the paisa),
 * and moves estimated/actual profit by the same amount. Safe to run any number of times.
 */
class AdAllocationService
{
    public function allocateRange(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            $spend = AdvertisingDaily::where('level', 'PRODUCT')->whereBetween('date', [$from, $to])->whereNotNull('product_id')
                ->selectRaw('marketplace_id, product_id, date, SUM(spend) s')->groupBy('marketplace_id', 'product_id', 'date')->get();
            $keys = [];
            foreach ($spend as $s) {
                $d = substr((string) $s->date, 0, 10);
                $keys["{$s->marketplace_id}|{$s->product_id}|{$d}"] = true;
                $this->applyGroup((int) $s->marketplace_id, (int) $s->product_id, $d, Money::p($s->s));
            }
            // lines that still carry ad cost although that day/product has no spend any more
            OrderProfit::whereBetween('order_date', [$from, $to])->where('ads_cost', '>', 0)->chunkById(500, function ($rows) use ($keys) {
                foreach ($rows as $r) {
                    if (! isset($keys["{$r->marketplace_id}|{$r->product_id}|{$r->order_date->toDateString()}"])) $this->setAds($r, 0);
                }
            });
        });
    }

    /** One product on one day (called whenever an order for it is created, changed or cancelled). */
    public function allocateKey(int $marketplaceId, int $productId, string $date): void
    {
        $s = AdvertisingDaily::where('level', 'PRODUCT')->where('marketplace_id', $marketplaceId)->where('product_id', $productId)->whereDate('date', $date)->sum('spend');
        $this->applyGroup($marketplaceId, $productId, $date, Money::p($s));
    }

    private function applyGroup(int $mp, int $productId, string $date, int $spend): void
    {
        $rows = OrderProfit::where('marketplace_id', $mp)->where('product_id', $productId)->whereDate('order_date', $date)->orderBy('id')->get();
        if ($rows->isEmpty()) return;                    // spend with no sales that day stays "unallocated" and is shown on the Profit page
        $qty = max((int) $rows->sum('qty'), 1); $left = $spend; $last = $rows->count() - 1;
        foreach ($rows as $i => $r) {
            $share = $i === $last ? $left : (int) round($spend * $r->qty / $qty);   // last line takes the remainder: total is exact
            $left -= $share;
            $this->setAds($r, $share);
        }
    }

    private function setAds(OrderProfit $r, int $newPaise): void
    {
        $old = Money::p($r->ads_cost);
        if ($old === $newPaise) return;
        $delta = $newPaise - $old;
        $upd = ['ads_cost' => Money::m($newPaise), 'estimated_profit' => Money::m(Money::p($r->estimated_profit) - $delta)];
        if ($r->actual_profit !== null) $upd['actual_profit'] = Money::m(Money::p($r->actual_profit) - $delta);
        OrderProfit::whereKey($r->id)->update($upd);
    }
}
