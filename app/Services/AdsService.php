<?php
namespace App\Services;

use App\Models\{AdvertisingDaily, Order, OrderProfit};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdsService
{
    /** marketplace id => the report level used for its totals (no double counting between report types) */
    public function chosenLevels(string $from, string $to, ?int $mp = null): array
    {
        $found = AdvertisingDaily::whereBetween('date', [$from, $to])->when($mp, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->select('marketplace_id', 'level')->distinct()->get()->groupBy('marketplace_id');
        $out = [];
        foreach ($found as $id => $rows) {
            $have = $rows->pluck('level')->all();
            foreach (AdvertisingDaily::TOTAL_PRIORITY as $lv) if (in_array($lv, $have, true)) { $out[$id] = $lv; break; }
        }
        return $out;
    }

    private function scope(Builder $q, array $chosen): Builder
    {
        if (! $chosen) return $q->whereRaw('1 = 0');
        return $q->where(function ($w) use ($chosen) {
            foreach ($chosen as $mp => $lv) $w->orWhere(fn ($x) => $x->where('advertising_daily.marketplace_id', $mp)->where('advertising_daily.level', $lv));
        });
    }

    private function base(array $f): Builder
    {
        $chosen = $this->chosenLevels($f['from'], $f['to'], $f['marketplace_id'] ?? null);
        return $this->scope(AdvertisingDaily::query()->whereBetween('advertising_daily.date', [$f['from'], $f['to']]), $chosen);
    }

    public function totalSpend(string $from, string $to, ?int $mp = null): float
    {
        return (float) $this->base(['from' => $from, 'to' => $to, 'marketplace_id' => $mp])->sum('spend');
    }

    private const SUMS = 'SUM(impressions) impressions, SUM(clicks) clicks, SUM(spend) spend, SUM(sales) sales, SUM(orders) orders, SUM(units) units';

    public function summary(array $f): array
    {
        $t = (clone $this->base($f))->selectRaw(self::SUMS)->first();
        $orderSales = (float) Order::where('status', '!=', 'CANCELLED')->whereDate('order_date', '>=', $f['from'])->whereDate('order_date', '<=', $f['to'])
            ->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('marketplace_id', $v))->sum('total_amount');
        return ['totals' => $this->metrics($t), 'totalSales' => $orderSales, 'tacos' => $orderSales > 0 ? round((float) $t->spend / $orderSales * 100, 1) : null,
            'daily' => (clone $this->base($f))->selectRaw('date, ' . self::SUMS)->groupBy('date')->orderBy('date')->get(),
            'campaignRows' => (clone $this->base($f))->join('advertising_campaigns as c', 'c.id', '=', 'advertising_daily.campaign_id')
                ->selectRaw('c.id cid, c.name campaign, ' . self::SUMS)->groupBy('c.id', 'c.name')->orderByRaw('SUM(spend) DESC')->get()];
    }

    /** derived ad metrics from a row of sums */
    public function metrics($r): array
    {
        $imp = (int) $r->impressions; $clk = (int) $r->clicks; $sp = (float) $r->spend; $sl = (float) $r->sales; $ord = (int) $r->orders;
        return ['impressions' => $imp, 'clicks' => $clk, 'spend' => $sp, 'sales' => $sl, 'orders' => $ord, 'units' => (int) $r->units,
            'ctr' => $imp > 0 ? round($clk / $imp * 100, 2) : null, 'cpc' => $clk > 0 ? round($sp / $clk, 2) : null,
            'cvr' => $clk > 0 ? round($ord / $clk * 100, 2) : null, 'acos' => $sl > 0 ? round($sp / $sl * 100, 1) : null, 'roas' => $sp > 0 ? round($sl / $sp, 2) : null];
    }

    /** keyword ($level KEYWORD) or search-term ($level TERM) performance, one row per target */
    public function targets(string $level, array $f, bool $paginate = true, ?string $sort = null)
    {
        $isKw = $level === 'KEYWORD';
        $q = AdvertisingDaily::query()->where('advertising_daily.level', $level)->whereBetween('advertising_daily.date', [$f['from'], $f['to']])
            ->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('advertising_daily.marketplace_id', $v))
            ->when($f['campaign_id'] ?? null, fn ($q, $v) => $q->where('advertising_daily.campaign_id', $v))
            ->join('advertising_campaigns as c', 'c.id', '=', 'advertising_daily.campaign_id');
        if ($isKw) {
            $q->join('advertising_keywords as k', 'k.id', '=', 'advertising_daily.keyword_id')
              ->when($f['q'] ?? null, fn ($q, $v) => $q->where('k.keyword', 'like', "%$v%"))
              ->selectRaw('k.id tid, k.keyword label, k.match_type, c.name campaign, ' . self::SUMS)->groupBy('k.id', 'k.keyword', 'k.match_type', 'c.name');
        } else {
            $q->join('advertising_search_terms as t', 't.id', '=', 'advertising_daily.search_term_id')
              ->when($f['q'] ?? null, fn ($q, $v) => $q->where('t.search_term', 'like', "%$v%"))
              ->selectRaw("t.id tid, t.search_term label, '' match_type, c.name campaign, " . self::SUMS)->groupBy('t.id', 't.search_term', 'c.name');
        }
        $orders = ['spend' => 'SUM(spend) DESC', 'sales' => 'SUM(sales) DESC', 'clicks' => 'SUM(clicks) DESC', 'orders' => 'SUM(orders) DESC',
            'acos_desc' => 'CASE WHEN SUM(sales) = 0 THEN 999999 ELSE SUM(spend) / SUM(sales) END DESC', 'wasted' => 'CASE WHEN SUM(orders) = 0 THEN SUM(spend) ELSE 0 END DESC'];
        $q->orderByRaw($orders[$sort ?? 'spend'] ?? $orders['spend']);
        return $paginate ? $q->paginate(50)->withQueryString() : $q;
    }

    /** Winners / wasted / potential for keywords and for search terms, plus product-level advice. Recommendations only. */
    public function insights(array $f): array
    {
        $target = (float) SettingsService::get('default_target_acos', 25);
        $wasteMin = (float) SettingsService::get('ads_waste_min_spend', 200);
        $out = ['target' => $target, 'wasteMin' => $wasteMin, 'levels' => []];
        foreach (['KEYWORD' => 'Keywords', 'TERM' => 'Search terms'] as $level => $label) {
            $base = fn () => $this->targets($level, $f, false);
            if (! (clone $base())->exists()) continue;
            $out['levels'][$level] = ['label' => $label,
                'winners' => $base()->havingRaw('SUM(sales) > 0 AND SUM(orders) >= 1 AND SUM(spend) * 100.0 / SUM(sales) <= CAST(? AS DECIMAL(15,4))', [$target])->reorder()->orderByRaw('SUM(sales) DESC')->limit(25)->get(),
                'wasted' => $base()->havingRaw('SUM(orders) = 0 AND SUM(sales) = 0 AND SUM(spend) >= CAST(? AS DECIMAL(15,4))', [$wasteMin])->reorder()->orderByRaw('SUM(spend) DESC')->limit(25)->get(),
                'potential' => $base()->havingRaw('SUM(clicks) >= 20 AND SUM(orders) >= 1 AND SUM(orders) * 100.0 / SUM(clicks) < 3 AND NOT (SUM(sales) > 0 AND SUM(spend) * 100.0 / SUM(sales) <= CAST(? AS DECIMAL(15,4)))', [$target])
                    ->reorder()->orderByRaw('SUM(clicks) DESC')->limit(25)->get()];
        }
        $out['products'] = $this->productAdvice($f, $target);
        return $out;
    }

    private function productAdvice(array $f, float $targetAcos): array
    {
        $targetMargin = (float) SettingsService::get('default_profit_margin', 20);
        $returnLimit = (float) SettingsService::get('default_return_threshold', 5);
        $p = OrderProfit::whereBetween('order_date', [$f['from'], $f['to']])->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->selectRaw('product_id, SUM(qty) units, SUM(revenue) revenue, SUM(return_loss) refunded, SUM(COALESCE(actual_profit, estimated_profit) + ads_cost) profit_before_ads, SUM(ads_cost) ads')
            ->groupBy('product_id')->get();
        if ($p->isEmpty()) return ['increase' => [], 'reduce' => []];
        $adRows = AdvertisingDaily::where('level', 'PRODUCT')->whereBetween('date', [$f['from'], $f['to']])->when($f['marketplace_id'] ?? null, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->selectRaw('product_id, SUM(spend) spend, SUM(sales) sales')->groupBy('product_id')->get()->keyBy('product_id');
        $names = \App\Models\Product::withTrashed()->whereIn('id', $p->pluck('product_id'))->get(['id', 'sku', 'name'])->keyBy('id');
        $units = $p->pluck('units')->map(fn ($u) => (int) $u)->sort()->values();
        $median = $units->count() ? $units[intdiv($units->count(), 2)] : 0;

        $inc = []; $red = [];
        foreach ($p as $r) {
            $rev = (float) $r->revenue; if ($rev <= 0) continue;
            $ad = $adRows[$r->product_id] ?? null; $spend = (float) ($ad->spend ?? 0); $adSales = (float) ($ad->sales ?? 0);
            $acos = $adSales > 0 ? $spend / $adSales * 100 : null;
            $profit = (float) $r->profit_before_ads - (float) $r->ads;      // profit after the ad cost already allocated
            $margin = $profit / $rev * 100; $marginBeforeAds = (float) $r->profit_before_ads / $rev * 100;
            $refundRate = (float) $r->refunded / $rev * 100;
            $row = ['sku' => $names[$r->product_id]->sku ?? '#' . $r->product_id, 'name' => $names[$r->product_id]->name ?? '', 'product_id' => $r->product_id,
                'units' => (int) $r->units, 'margin' => round($margin, 1), 'spend' => $spend, 'acos' => $acos === null ? null : round($acos, 1), 'refund' => round($refundRate, 1)];
            if ($marginBeforeAds >= $targetMargin && (int) $r->units <= $median && ($spend == 0 || ($acos !== null && $acos <= $targetAcos))) {
                $inc[] = $row + ['why' => 'Healthy margin before ads (' . round($marginBeforeAds, 1) . '%), sells less than most products, ' . ($spend == 0 ? 'no ad spend yet' : 'ads run under your target ACOS') . '. Candidate for more advertising.'];
            }
            if ($spend > 0 && ($margin < 0 || ($acos !== null && $acos > $targetAcos && $margin < $targetMargin) || $refundRate >= $returnLimit)) {
                $why = [];
                if ($margin < 0) $why[] = 'losing money after ads';
                if ($acos !== null && $acos > $targetAcos && $margin < $targetMargin) $why[] = 'ACOS ' . round($acos, 1) . '% is above target ' . $targetAcos . '% and margin is thin';
                if ($refundRate >= $returnLimit) $why[] = 'refunds are ' . round($refundRate, 1) . '% of sales (limit ' . $returnLimit . '%)';
                $red[] = $row + ['why' => ucfirst(implode('; ', $why)) . '. Consider reducing bids or pausing.'];
            }
        }
        usort($inc, fn ($a, $b) => $b['margin'] <=> $a['margin']);
        usort($red, fn ($a, $b) => $b['spend'] <=> $a['spend']);
        return ['increase' => array_slice($inc, 0, 25), 'reduce' => array_slice($red, 0, 25)];
    }
}
