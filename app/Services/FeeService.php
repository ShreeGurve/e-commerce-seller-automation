<?php
namespace App\Services;

use App\Models\{Category, FeeRule, Product};
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Turns the configurable fee_rules into fee amounts for one order line. Nothing is hard-coded here. */
class FeeService
{
    private array $rules = [];   // marketplace id => Collection<FeeRule>
    private array $chain = [];   // category id => [id, parent id, grandparent id ...]

    /** @return array<int,array{type:string,amount:int,tax:int,rule_id:int}> amounts in paise */
    public function estimate(Product $product, int $marketplaceId, int $linePaise, int $taxPaise, int $qty, Carbon $date): array
    {
        $chain = $product->category_id ? $this->ancestry($product->category_id) : [];
        $w = $product->weight_g;
        $gst = (float) SettingsService::get('fee_gst_rate', 18);   // set 0 when you claim input credit on fee GST
        $d = $date->toDateString();

        $matches = $this->rulesFor($marketplaceId)->filter(function (FeeRule $r) use ($d, $chain, $w) {
            if ($r->effective_from->toDateString() > $d) return false;
            if ($r->effective_to && $r->effective_to->toDateString() < $d) return false;
            if ($r->category_id !== null && ! in_array($r->category_id, $chain, true)) return false;
            if ($r->weight_min_g === null && $r->weight_max_g === null) return true;
            return $w !== null && ($r->weight_min_g === null || $w >= $r->weight_min_g) && ($r->weight_max_g === null || $w <= $r->weight_max_g);
        });

        $out = [];
        foreach ($matches->groupBy('fee_type') as $type => $cands) {
            // ONE rule per fee type: nearest category first, then weight-slab rules, then newest.
            $rule = $cands->sort(function (FeeRule $a, FeeRule $b) use ($chain) {
                $ca = $a->category_id === null ? 999 : array_search($a->category_id, $chain, true);
                $cb = $b->category_id === null ? 999 : array_search($b->category_id, $chain, true);
                return [$ca, $a->weight_min_g === null && $a->weight_max_g === null ? 1 : 0, $b->effective_from->timestamp]
                   <=> [$cb, $b->weight_min_g === null && $b->weight_max_g === null ? 1 : 0, $a->effective_from->timestamp];
            })->first();

            $base = $rule->applies_on === 'PRICE_EX_TAX' ? $linePaise - $taxPaise : $linePaise;
            $amount = (int) round($base * (float) $rule->percentage / 100) + Money::p($rule->fixed_amount) * $qty;
            if ($amount <= 0) continue;
            $out[] = ['type' => $type, 'amount' => $amount, 'tax' => (int) round($amount * $gst / 100), 'rule_id' => $rule->id];
        }
        return $out;
    }

    private function rulesFor(int $mp): Collection
    {
        return $this->rules[$mp] ??= FeeRule::where('marketplace_id', $mp)->where('is_active', true)->get();
    }

    private function ancestry(int $categoryId): array
    {
        if (isset($this->chain[$categoryId])) return $this->chain[$categoryId];
        $ids = []; $id = $categoryId;
        while ($id && ! in_array($id, $ids, true) && count($ids) < 10) { $ids[] = $id; $id = Category::where('id', $id)->value('parent_id'); }
        return $this->chain[$categoryId] = $ids;
    }
}
