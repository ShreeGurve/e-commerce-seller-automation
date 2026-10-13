<?php
namespace App\Services;

use App\Models\{CompetitorPrice, Marketplace, PriceHistory, Product, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price = what the customer pays, GST included. Profit per unit at price P:
 *   P / (1 + GST)  -  unit cost  -  marketplace fees(P)  -  ad cost per unit  -  return cost per unit
 * Fees are linear in P (percentage + fixed), so the break-even / minimum / target prices are solved exactly, not guessed.
 * Fees, ad cost and return cost come from YOUR fee rules and YOUR order history. Advice only: no price is ever changed for you.
 */
class PricingService
{
    public function __construct(private FeeService $fees) {}

    /**
     * Pure maths (no database). All money in paise. $a = fee share of price, $b = fixed fee per unit (both include GST on fees).
     * @return array{break_even:?int,min_price:?int,target_price:?int,at:?array,status:string,max_discount:?float}
     */
    public function economics(float $gstPct, int $unit, float $a, float $b, float $ads, float $ret, float $minMargin, float $targetMargin, ?int $current): array
    {
        $g = $gstPct / 100; $fixed = $unit + $b + $ads + $ret;
        $solve = function (float $m) use ($g, $a, $fixed): ?int {          // price (rounded UP to a whole rupee) that earns margin m% on ex-GST revenue
            $den = (1 - $m / 100) / (1 + $g) - $a;
            return $den > 1e-9 ? (int) (ceil($fixed / $den / 100) * 100) : null;
        };
        $at = function (int $p) use ($g, $unit, $a, $b, $ads, $ret): array {
            $rev = $p / (1 + $g); $fees = $a * $p + $b; $profit = $rev - $unit - $fees - $ads - $ret;
            return ['revenue' => $rev, 'fees' => $fees, 'profit' => $profit, 'margin' => $rev > 0 ? $profit / $rev * 100 : null];
        };
        $be = $solve(0.0); $min = $solve($minMargin); $tg = $solve($targetMargin);
        $r = ['break_even' => $be, 'min_price' => $min, 'target_price' => $tg, 'at' => null, 'status' => $be === null ? 'NO_BREAK_EVEN' : 'NO_PRICE', 'max_discount' => null];
        if ($current && $current > 0) {
            $r['at'] = $x = $at($current);
            $r['status'] = match (true) {
                $be === null => 'NO_BREAK_EVEN', $x['profit'] < 0 => 'LOSS', $min !== null && $current < $min => 'BELOW_MIN',
                $x['margin'] !== null && $x['margin'] < $targetMargin => 'BELOW_TARGET', default => 'OK',
            };
            $r['max_discount'] = $min !== null && $current > $min ? round(($current - $min) / $current * 100, 1) : 0.0;
        }
        return $r;
    }

    /** latest price you recorded for this channel, else the product's master selling price (GST incl.), else null */
    public function currentPrice(Product $p, ?int $marketplaceId): ?array
    {
        $h = PriceHistory::where('product_id', $p->id)->where('marketplace_id', $marketplaceId)->orderByDesc('effective_on')->orderByDesc('id')->first();
        if ($h) return ['price' => (string) $h->price, 'source' => 'Recorded ' . $h->effective_on->format('d M Y')];
        return $p->default_selling_price !== null ? ['price' => (string) $p->default_selling_price, 'source' => 'Product master price'] : null;
    }

    /** fee(P) = a*P + b, found by asking the real fee rules at two prices (so every rule, slab and category is honoured) */
    private function feeCoefficients(Product $p, int $mp): array
    {
        $g = (float) $p->gst_rate;
        $f = function (int $line) use ($p, $mp, $g): int {
            $tax = (int) round($line * $g / (100 + $g));
            return array_sum(array_map(fn ($x) => $x['amount'] + $x['tax'], $this->fees->estimate($p, $mp, $line, $tax, 1, now())));
        };
        $p1 = 1000000; $p2 = 2000000; $f1 = $f($p1); $f2 = $f($p2);
        $a = ($f2 - $f1) / ($p2 - $p1);
        return [$a, $f1 - $a * $p1];
    }

    /** average ad cost and return cost per unit SOLD in the look-back window (paise), from real order history */
    private function history(int $productId, int $mp): array
    {
        $days = (int) SettingsService::get('pricing_lookback_days', 90);
        $r = DB::table('order_profits as p')->join('order_items as i', 'i.id', '=', 'p.order_item_id')
            ->where('p.product_id', $productId)->where('p.marketplace_id', $mp)->where('p.order_date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('SUM(p.qty) q, SUM(p.ads_cost) ads, SUM(p.return_loss) rl, SUM(p.cogs) c, SUM((i.cogs_at_sale + i.packaging_at_sale + i.other_cost_at_sale) * i.quantity) full')->first();
        $q = (int) ($r->q ?? 0);
        if ($q <= 0) return ['ads' => 0.0, 'ret' => 0.0, 'units' => 0, 'days' => $days];
        $restocked = max((float) $r->full - (float) $r->c, 0);                // cost that came back as sellable stock
        return ['ads' => (float) $r->ads * 100 / $q, 'ret' => max(((float) $r->rl - $restocked) * 100 / $q, 0), 'units' => $q, 'days' => $days];
    }

    public function analyze(Product $p, Marketplace $mp, ?string $priceOverride = null): array
    {
        $cur = $this->currentPrice($p, $mp->id);
        $curPaise = $priceOverride !== null && $priceOverride !== '' ? Money::p($priceOverride) : ($cur ? Money::p($cur['price']) : null);
        [$a, $b] = $this->feeCoefficients($p, $mp->id);
        $h = $this->history($p->id, $mp->id); $unit = Money::p($p->totalUnitCost());
        $minM = (float) SettingsService::get('pricing_min_margin_percent', 5); $tgtM = (float) SettingsService::get('default_profit_margin', 20);
        $e = $this->economics((float) $p->gst_rate, $unit, $a, $b, $h['ads'], $h['ret'], $minM, $tgtM, $curPaise);

        $win = (int) SettingsService::get('pricing_competitor_days', 30);
        $comp = CompetitorPrice::where('product_id', $p->id)->where('marketplace_id', $mp->id)->where('captured_on', '>=', now()->subDays($win)->toDateString())
            ->orderByDesc('captured_on')->orderByDesc('id')->get()->unique(fn ($c) => mb_strtolower($c->competitor_name))->values();
        $lowest = $comp->isEmpty() ? null : (float) $comp->min('competitor_price');
        return $e + ['product' => $p, 'marketplace' => $mp, 'current' => $curPaise, 'source' => $priceOverride ? 'What-if price' : ($cur['source'] ?? null), 'unit_cost' => $unit,
            'fee_a' => $a, 'fee_b' => $b, 'no_fees' => $a == 0 && $b == 0, 'ads_per_unit' => $h['ads'], 'return_per_unit' => $h['ret'], 'history_units' => $h['units'], 'history_days' => $h['days'],
            'min_margin' => $minM, 'target_margin' => $tgtM, 'competitors' => $comp, 'competitor_low' => $lowest,
            'vs_competitor' => ($lowest !== null && $curPaise) ? round(($curPaise / 100 - $lowest) / $lowest * 100, 1) : null];
    }

    public function recordPrice(Product $p, ?int $marketplaceId, string $price, ?string $reason, ?string $effectiveOn, User $user): PriceHistory
    {
        $paise = Money::p($price);
        if ($paise <= 0) throw ValidationException::withMessages(['price' => 'Price must be more than zero.']);
        return DB::transaction(function () use ($p, $marketplaceId, $paise, $reason, $effectiveOn, $user) {
            $prev = $this->currentPrice($p, $marketplaceId);
            $h = PriceHistory::create(['product_id' => $p->id, 'marketplace_id' => $marketplaceId, 'price' => Money::m($paise), 'previous_price' => $prev['price'] ?? null,
                'reason' => $reason, 'source' => $marketplaceId ? 'MANUAL' : 'MASTER', 'effective_on' => $effectiveOn ?: now()->toDateString(), 'changed_by' => $user->id]);
            ActivityLogger::log('price.recorded', $p, ['price' => $prev['price'] ?? null], ['price' => Money::m($paise), 'marketplace_id' => $marketplaceId]);
            return $h;
        });
    }

    public function addCompetitor(Product $p, Marketplace $mp, string $name, string $price, string $capturedOn, ?string $source, ?string $url, User $user): CompetitorPrice
    {
        $paise = Money::p($price);
        if ($paise <= 0) throw ValidationException::withMessages(['competitor_price' => 'Price must be more than zero.']);
        $our = $this->currentPrice($p, $mp->id);
        $row = CompetitorPrice::updateOrCreate(['product_id' => $p->id, 'marketplace_id' => $mp->id, 'competitor_name' => $name, 'captured_on' => $capturedOn],
            ['competitor_price' => Money::m($paise), 'our_price' => $our['price'] ?? null, 'source' => $source, 'url' => $url, 'created_by' => $user->id]);
        ActivityLogger::log('competitor_price.saved', $p, null, ['competitor' => $name, 'price' => Money::m($paise), 'date' => $capturedOn]);
        return $row;
    }
}
