<?php
namespace App\Services;

use App\Models\{Expense, Marketplace, ProductReturn};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Every dashboard number comes from the daily summary tables (fast), except top / lowest products and returns. */
class DashboardService
{
    /** @param array{from:string,to:string,marketplace_id?:?int} $f */
    public function build(array $f): array
    {
        $mp = $f['marketplace_id'] ?? null;
        $tot = fn (string $table, string $cols, string $from, string $to) => DB::table($table)->whereBetween('date', [$from, $to])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))->selectRaw($cols)->first();

        $s = $tot('daily_sales', 'COALESCE(SUM(orders),0) orders, COALESCE(SUM(units),0) units, COALESCE(SUM(gross_sales),0) sales, COALESCE(SUM(cancelled_orders),0) cancelled', $f['from'], $f['to']);
        $p = $tot('daily_profit', 'COALESCE(SUM(revenue),0) revenue, COALESCE(SUM(cogs),0) cogs, COALESCE(SUM(fees),0) fees, COALESCE(SUM(refunds),0) refunds, COALESCE(SUM(recovery),0) recovery, COALESCE(SUM(ads),0) ads, COALESCE(SUM(profit),0) profit, COALESCE(SUM(lines),0) lines, COALESCE(SUM(actual_lines),0) actual', $f['from'], $f['to']);
        $a = $tot('daily_advertising', 'COALESCE(SUM(impressions),0) imp, COALESCE(SUM(clicks),0) clicks, COALESCE(SUM(spend),0) spend, COALESCE(SUM(sales),0) sales, COALESCE(SUM(orders),0) orders', $f['from'], $f['to']);
        $expenses = (float) Expense::whereBetween('expense_date', [$f['from'], $f['to']])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))->sum('amount');
        $returns = ProductReturn::whereBetween('return_date', [$f['from'], $f['to']])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))->count();

        $sales = (float) $s->sales; $orders = (int) $s->orders; $revenue = (float) $p->revenue; $profit = (float) $p->profit; $adSpend = (float) $a->spend; $adSales = (float) $a->sales;
        $unalloc = max($adSpend - (float) $p->ads, 0);                  // ad money no sale could carry: still a cost
        $kpi = [
            'sales' => $sales, 'orders' => $orders, 'units' => (int) $s->units, 'cancelled' => (int) $s->cancelled, 'revenue' => $revenue, 'cogs' => (float) $p->cogs, 'fees' => (float) $p->fees,
            'refunds' => (float) $p->refunds, 'recovery' => (float) $p->recovery, 'adsAllocated' => (float) $p->ads, 'adSpend' => $adSpend, 'adSales' => $adSales, 'unallocated' => $unalloc,
            'orderProfit' => $profit, 'expenses' => $expenses, 'net' => $profit - $expenses - $unalloc, 'margin' => $revenue > 0 ? $profit / $revenue * 100 : null,
            'acos' => $adSales > 0 ? $adSpend / $adSales * 100 : null, 'roas' => $adSpend > 0 ? $adSales / $adSpend : null, 'tacos' => $sales > 0 ? $adSpend / $sales * 100 : null,
            'clicks' => (int) $a->clicks, 'impressions' => (int) $a->imp, 'returns' => $returns, 'returnRate' => $orders > 0 ? $returns / $orders * 100 : null,
            'cancelRate' => ($orders + (int) $s->cancelled) > 0 ? (int) $s->cancelled / ($orders + (int) $s->cancelled) * 100 : null,
            'settledPct' => (int) $p->lines > 0 ? (int) $p->actual / (int) $p->lines * 100 : null,
        ];

        // the period right before this one, same length, for "up / down" arrows
        $days = max((int) Carbon::parse($f['from'])->diffInDays(Carbon::parse($f['to'])) + 1, 1);
        $pTo = Carbon::parse($f['from'])->subDay()->toDateString(); $pFrom = Carbon::parse($pTo)->subDays($days - 1)->toDateString();
        $ps = $tot('daily_sales', 'COALESCE(SUM(orders),0) orders, COALESCE(SUM(gross_sales),0) sales', $pFrom, $pTo);
        $pp = $tot('daily_profit', 'COALESCE(SUM(profit),0) profit', $pFrom, $pTo);
        $delta = fn (float $now, float $prev) => $prev != 0.0 ? round(($now - $prev) / abs($prev) * 100, 1) : null;
        $kpi['delta'] = ['sales' => $delta($sales, (float) $ps->sales), 'orders' => $delta((float) $orders, (float) $ps->orders), 'profit' => $delta($profit, (float) $pp->profit)];

        return ['kpi' => $kpi, 'series' => $this->series($f, $mp), 'channels' => $this->channels($f, $mp), 'products' => $this->products($f, $mp), 'days' => $days];
    }

    /** one value per calendar day (zeros filled in) for the charts */
    private function series(array $f, ?int $mp): array
    {
        $by = fn (string $table, string $cols) => DB::table($table)->whereBetween('date', [$f['from'], $f['to']])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->selectRaw("date, {$cols}")->groupBy('date')->get()->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $s = $by('daily_sales', 'SUM(orders) orders, SUM(gross_sales) sales'); $p = $by('daily_profit', 'SUM(profit) profit, SUM(refunds) refunds'); $a = $by('daily_advertising', 'SUM(spend) spend, SUM(sales) adsales');
        $out = ['labels' => [], 'sales' => [], 'orders' => [], 'profit' => [], 'refunds' => [], 'adSpend' => [], 'adSales' => []];
        for ($d = Carbon::parse($f['from']), $n = 0; $d->lte(Carbon::parse($f['to'])) && $n < 731; $d->addDay(), $n++) {
            $k = $d->toDateString();
            $out['labels'][] = $d->format('d M'); $out['sales'][] = round((float) ($s[$k]->sales ?? 0), 2); $out['orders'][] = (int) ($s[$k]->orders ?? 0);
            $out['profit'][] = round((float) ($p[$k]->profit ?? 0), 2); $out['refunds'][] = round((float) ($p[$k]->refunds ?? 0), 2);
            $out['adSpend'][] = round((float) ($a[$k]->spend ?? 0), 2); $out['adSales'][] = round((float) ($a[$k]->adsales ?? 0), 2);
        }
        return $out;
    }

    private function channels(array $f, ?int $mp): array
    {
        $g = fn (string $table, string $cols) => DB::table($table)->whereBetween('date', [$f['from'], $f['to']])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->selectRaw("marketplace_id, {$cols}")->groupBy('marketplace_id')->get()->keyBy('marketplace_id');
        $s = $g('daily_sales', 'SUM(orders) orders, SUM(gross_sales) sales'); $p = $g('daily_profit', 'SUM(profit) profit, SUM(refunds) refunds, SUM(ads) ads, SUM(revenue) revenue'); $a = $g('daily_advertising', 'SUM(spend) spend, SUM(sales) adsales');
        $ret = ProductReturn::whereBetween('return_date', [$f['from'], $f['to']])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))->select('marketplace_id', DB::raw('count(*) c'))->groupBy('marketplace_id')->pluck('c', 'marketplace_id');
        $rows = [];
        foreach (Marketplace::active()->when($mp, fn ($q, $v) => $q->where('id', $v))->get() as $m) {
            $sp = (float) ($a[$m->id]->spend ?? 0); $as = (float) ($a[$m->id]->adsales ?? 0); $orders = (int) ($s[$m->id]->orders ?? 0); $rev = (float) ($p[$m->id]->revenue ?? 0); $pr = (float) ($p[$m->id]->profit ?? 0);
            $rows[] = ['id' => $m->id, 'name' => $m->name, 'orders' => $orders, 'sales' => (float) ($s[$m->id]->sales ?? 0), 'profit' => $pr, 'margin' => $rev > 0 ? $pr / $rev * 100 : null,
                'adSpend' => $sp, 'acos' => $as > 0 ? $sp / $as * 100 : null, 'returns' => (int) ($ret[$m->id] ?? 0), 'refunded' => (float) ($p[$m->id]->refunds ?? 0)];
        }
        return $rows;
    }

    private function products(array $f, ?int $mp): array
    {
        $q = fn () => DB::table('order_profits as p')->join('products as pr', 'pr.id', '=', 'p.product_id')->whereBetween('p.order_date', [$f['from'], $f['to']])->when($mp, fn ($x, $v) => $x->where('p.marketplace_id', $v))
            ->selectRaw('p.product_id, pr.sku, pr.name, SUM(p.qty) units, SUM(p.revenue) revenue, SUM(COALESCE(p.actual_profit, p.estimated_profit)) profit')->groupBy('p.product_id', 'pr.sku', 'pr.name');
        return ['top' => $q()->orderByRaw('profit DESC')->limit(5)->get(), 'worst' => $q()->orderByRaw('profit ASC')->limit(5)->get()];
    }
}
