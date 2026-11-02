<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Keeps daily_sales / daily_profit / daily_advertising correct for one (day, channel) at a time.
 * Always recomputed from the real tables, never incremented, so it can never drift: refreshing twice gives the same answer.
 * Bound as a singleton so big jobs can batch: begin() ... touch() many times ... flush() refreshes each affected day once.
 */
class SummaryService
{
    private bool $batching = false;
    private array $dirty = [];

    public function begin(): void { $this->batching = true; }

    public function touch(string $date, int $marketplaceId): void
    {
        $date = substr($date, 0, 10);
        if ($this->batching) { $this->dirty["{$date}|{$marketplaceId}"] = [$date, $marketplaceId]; return; }
        $this->refreshDay($date, $marketplaceId);
    }

    public function flush(): int
    {
        $this->batching = false; $n = 0;
        foreach ($this->dirty as [$d, $m]) { $this->refreshDay($d, $m); $n++; }
        $this->dirty = [];
        return $n;
    }

    public function refreshDay(string $date, int $mp): void
    {
        $now = now();
        $start = $date . ' 00:00:00'; $end = $date . ' 23:59:59';

        $o = DB::table('orders')->where('marketplace_id', $mp)->whereNull('deleted_at')->whereBetween('order_date', [$start, $end])
            ->selectRaw("SUM(CASE WHEN status <> 'CANCELLED' THEN 1 ELSE 0 END) orders, SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) cancelled, SUM(CASE WHEN status <> 'CANCELLED' THEN total_amount ELSE 0 END) sales")->first();
        $units = (int) DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.marketplace_id', $mp)->whereNull('o.deleted_at')
            ->where('o.status', '<>', 'CANCELLED')->whereBetween('o.order_date', [$start, $end])->sum('i.quantity');
        $this->save('daily_sales', $date, $mp, ['orders' => (int) $o->orders, 'units' => $units, 'gross_sales' => (float) $o->sales, 'cancelled_orders' => (int) $o->cancelled], $now);

        $p = DB::table('order_profits')->where('marketplace_id', $mp)->where('order_date', $date)
            ->selectRaw('COUNT(*) l, SUM(qty) units, SUM(revenue) revenue, SUM(cogs) cogs, SUM(fees) fees, SUM(return_loss) refunds, SUM(recovery) recovery, SUM(ads_cost) ads, SUM(COALESCE(actual_profit, estimated_profit)) profit, SUM(CASE WHEN actual_profit IS NOT NULL THEN 1 ELSE 0 END) actual')->first();
        $this->save('daily_profit', $date, $mp, ['lines' => (int) $p->l, 'units' => (int) $p->units, 'revenue' => (float) $p->revenue, 'cogs' => (float) $p->cogs, 'fees' => (float) $p->fees,
            'refunds' => (float) $p->refunds, 'recovery' => (float) $p->recovery, 'ads' => (float) $p->ads, 'profit' => (float) $p->profit, 'actual_lines' => (int) $p->actual], $now);

        $level = app(AdsService::class)->chosenLevels($date, $date, $mp)[$mp] ?? null;       // one report level per channel, no double counting
        $a = $level ? DB::table('advertising_daily')->where('marketplace_id', $mp)->where('date', $date)->where('level', $level)
            ->selectRaw('SUM(impressions) imp, SUM(clicks) clk, SUM(spend) spend, SUM(sales) sales, SUM(orders) orders, SUM(units) units')->first() : null;
        $this->save('daily_advertising', $date, $mp, ['level' => $level ?? 'CAMPAIGN', 'impressions' => (int) ($a->imp ?? 0), 'clicks' => (int) ($a->clk ?? 0), 'spend' => (float) ($a->spend ?? 0),
            'sales' => (float) ($a->sales ?? 0), 'orders' => (int) ($a->orders ?? 0), 'units' => (int) ($a->units ?? 0)], $now, ['impressions', 'clicks', 'spend', 'sales', 'orders', 'units']);
    }

    /** an all-zero day has no row (keeps the tables small); otherwise insert-or-update */
    private function save(string $table, string $date, int $mp, array $vals, $now, ?array $zeroCheck = null): void
    {
        $key = ['date' => $date, 'marketplace_id' => $mp];
        $nums = $zeroCheck ? array_intersect_key($vals, array_flip($zeroCheck)) : array_filter($vals, 'is_numeric');
        if (! array_filter($nums, fn ($v) => (float) $v != 0.0)) { DB::table($table)->where($key)->delete(); return; }
        DB::table($table)->upsert([$key + $vals + ['updated_at' => $now]], ['date', 'marketplace_id'], array_merge(array_keys($vals), ['updated_at']));
    }

    /** every (day, channel) that has source data or an old summary row inside the range */
    public function rebuildRange(string $from, string $to): int
    {
        $from = substr($from, 0, 10); $to = substr($to, 0, 10); $pairs = [];
        $add = function ($rows, string $d, string $m) use (&$pairs) { foreach ($rows as $r) { $day = substr((string) $r->{$d}, 0, 10); $pairs["{$day}|{$r->{$m}}"] = [$day, (int) $r->{$m}]; } };
        $add(DB::table('orders')->whereNull('deleted_at')->whereBetween('order_date', [$from . ' 00:00:00', $to . ' 23:59:59'])->selectRaw('DATE(order_date) d, marketplace_id m')->distinct()->get(), 'd', 'm');
        $add(DB::table('order_profits')->whereBetween('order_date', [$from, $to])->select('order_date as d', 'marketplace_id as m')->distinct()->get(), 'd', 'm');
        $add(DB::table('advertising_daily')->whereBetween('date', [$from, $to])->select('date as d', 'marketplace_id as m')->distinct()->get(), 'd', 'm');
        foreach (['daily_sales', 'daily_profit', 'daily_advertising'] as $t) $add(DB::table($t)->whereBetween('date', [$from, $to])->select('date as d', 'marketplace_id as m')->distinct()->get(), 'd', 'm');
        foreach ($pairs as [$d, $m]) $this->refreshDay($d, $m);
        return count($pairs);
    }

    /** whole history (used by the CLI command and the dashboard button) */
    public function rebuildAll(): int
    {
        $min = collect([DB::table('orders')->min('order_date'), DB::table('order_profits')->min('order_date'), DB::table('advertising_daily')->min('date')])->filter()->map(fn ($d) => substr((string) $d, 0, 10))->min();
        return $min ? $this->rebuildRange($min, now()->toDateString()) : 0;
    }

    /** end-of-day stock per product (so history of stock levels exists) */
    public function snapshotInventory(?string $date = null): int
    {
        $date = $date ?: now()->toDateString(); $n = 0;
        DB::table('inventory')->select('product_id', 'available_qty', 'damaged_qty')->orderBy('product_id')->chunk(500, function ($rows) use ($date, &$n) {
            DB::table('daily_inventory')->upsert($rows->map(fn ($r) => ['date' => $date, 'product_id' => $r->product_id, 'available_qty' => $r->available_qty, 'damaged_qty' => $r->damaged_qty])->all(), ['date', 'product_id'], ['available_qty', 'damaged_qty']);
            $n += $rows->count();
        });
        return $n;
    }
}
