<?php
namespace App\Services;

use App\Models\{Claim, Inventory, Listing, Order, ProductReturn, ReconciliationIssue, User};
use Illuminate\Support\Facades\DB;

/**
 * "What should I do today?" Every item is a real, current problem with a link to fix it. Only items the user may open are shown.
 * Severity 3 = money at risk now, 2 = needs attention, 1 = housekeeping.
 */
class DecisionService
{
    /** @return array<int,array{sev:int,icon:string,title:string,detail:string,url:string}> */
    public function actions(User $u): array
    {
        $out = []; $add = function (int $sev, string $icon, string $title, string $detail, string $url) use (&$out) { $out[] = compact('sev', 'icon', 'title', 'detail', 'url'); };
        $guard = function (string $perm, callable $fn) use ($u) { if ($u->can($perm)) { try { $fn(); } catch (\Throwable $e) { report($e); } } };   // one broken check never hides the rest
        $since = now()->subDays(29)->toDateString();
        $money = fn ($v) => '₹' . number_format((float) $v, 0);

        $guard('claims.view', function () use ($add) {
            if (($n = Claim::urgentCount()) > 0) $add(3, 'bi-alarm', "{$n} claim(s) at or past the deadline", 'File or appeal them now or the money is lost.', route('claims.index', ['deadline' => 'urgent']));
        });
        $guard('inventory.view', function () use ($add, $since) {
            $out = Inventory::where('available_qty', '<=', 0)->whereIn('product_id', DB::table('order_profits')->where('order_date', '>=', $since)->select('product_id'))->count();
            if ($out > 0) $add(3, 'bi-x-octagon', "{$out} product(s) sold recently are out of stock", 'You are losing sales. Reorder now.', route('inventory.index', ['stock' => 'out']));
            $low = Inventory::where('minimum_stock', '>', 0)->where('available_qty', '>', 0)->whereColumn('available_qty', '<=', 'minimum_stock')->count();
            if ($low > 0) $add(2, 'bi-exclamation-triangle', "{$low} product(s) are below minimum stock", 'Plan a purchase before they run out.', route('inventory.index', ['stock' => 'low']));
        });
        $guard('finance.view', function () use ($add, $since, $money) {
            $loss = DB::table('order_profits')->where('order_date', '>=', $since)->selectRaw('product_id, SUM(COALESCE(actual_profit, estimated_profit)) p')->groupBy('product_id')->havingRaw('SUM(COALESCE(actual_profit, estimated_profit)) < 0')->get();
            if ($loss->count() > 0) $add(3, 'bi-graph-down-arrow', "{$loss->count()} product(s) lost money in the last 30 days", 'Total ' . $money(abs($loss->sum('p'))) . ' lost. Raise the price, cut costs or stop selling.', route('profit.index', ['sort' => 'profit_asc']));
            if (($n = ReconciliationIssue::where('status', 'OPEN')->count()) > 0) $add(2, 'bi-check2-all', "{$n} open reconciliation issue(s)", 'Missing payouts, wrong fees or unpaid claims.', route('reconciliation.index'));
        });
        $guard('advertising.view', function () use ($add, $money) {
            $waste = (float) SettingsService::get('ads_waste_min_spend', 200);
            $rows = app(AdsService::class)->targets('KEYWORD', ['from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString(), 'marketplace_id' => null], false)
                ->havingRaw('SUM(orders) = 0 AND SUM(sales) = 0 AND SUM(spend) >= CAST(? AS DECIMAL(15,4))', [$waste])->limit(500)->get();
            if ($rows->count() > 0) $add(2, 'bi-megaphone', "{$rows->count()} keyword(s) spent money and never sold", $money($rows->sum('spend')) . ' wasted in 30 days. Pause or negate them.', route('ads.insights'));
        });
        $guard('returns.view', function () use ($add) {
            if (($n = ProductReturn::where('status', 'REQUESTED')->count()) > 0) $add(2, 'bi-arrow-return-left', "{$n} return(s) waiting for inspection", 'Inspect them so stock and claims are correct.', route('returns.index', ['status' => 'REQUESTED']));
        });
        $guard('orders.view', function () use ($add) {
            if (($n = Order::where('status', 'IMPORTED')->count()) > 0) $add(1, 'bi-inbox', "{$n} imported order(s) need review", 'Confirm them so they move through the pipeline.', route('orders.index', ['status' => 'IMPORTED']));
        });
        $guard('listings.view', function () use ($add) {
            if (($n = Listing::whereHas('current', fn ($c) => $c->where('score', '<', 70))->count()) > 0) $add(1, 'bi-card-text', "{$n} listing(s) score under 70", 'Better copy sells more at the same ad cost.', route('listings.index', ['low' => 1]));
        });
        usort($out, fn ($a, $b) => $b['sev'] <=> $a['sev']);
        return $out;
    }
}
