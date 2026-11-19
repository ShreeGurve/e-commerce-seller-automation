<?php

namespace App\Services;

use App\Models\DynamicPricingRule;
use App\Models\DynamicPricingRun;
use App\Models\PricingRecommendation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Generates approval-gated internal recommendations only. It never publishes marketplace prices. */
class DynamicPricingService
{
    public function __construct(private readonly PricingService $pricing) {}

    public function run(?int $businessId = null, ?int $marketplaceId = null): DynamicPricingRun
    {
        $run = DynamicPricingRun::create(['business_id' => $businessId, 'marketplace_id' => $marketplaceId, 'status' => 'RUNNING', 'started_at' => now()]);
        try {
            $query = DynamicPricingRule::query()->with(['product.currentCosts', 'product.marketplaceSettings', 'product.brand', 'marketplace'])
                ->where('enabled', true)->orderBy('id');
            if ($businessId !== null) $query->where('business_id', $businessId);
            if ($marketplaceId !== null) $query->where('marketplace_id', $marketplaceId);

            foreach ($query->get() as $rule) {
                $run->rules_checked++;
                try {
                    $created = $this->recommend($rule);
                    $created ? $run->recommendations_created++ : $run->skipped_count++;
                } catch (Throwable $e) {
                    $run->skipped_count++;
                }
            }
            $run->status = 'COMPLETED';
            $run->summary = sprintf('%d rules checked; %d recommendations created; %d skipped. No marketplace prices were changed.', $run->rules_checked, $run->recommendations_created, $run->skipped_count);
        } catch (Throwable $e) {
            $run->status = 'FAILED';
            $run->summary = mb_substr($e->getMessage(), 0, 2000);
        }
        $run->finished_at = now();
        $run->save();
        return $run->refresh();
    }

    private function recommend(DynamicPricingRule $rule): bool
    {
        $product = $rule->product;
        $marketplace = $rule->marketplace;
        if (!$product || !$marketplace || !$product->brand || ($rule->business_id && (int) $product->brand->business_id !== (int) $rule->business_id)) return false;

        // Do not create duplicate pending/approved proposals for the same product and channel.
        if (PricingRecommendation::query()->where('product_id', $product->id)->where('marketplace_id', $marketplace->id)->whereIn('status', ['pending', 'approved'])->exists()) return false;

        $analysis = $this->pricing->analyze($product, $marketplace);
        $minimumSafe = $analysis['min_price'];
        if ($minimumSafe === null) return false;
        $candidate = match ($rule->strategy) {
            'COMPETITOR_MATCH' => $analysis['competitor_low'] !== null ? Money::p((string) $analysis['competitor_low']) : null,
            'COMPETITOR_UNDERCUT' => $analysis['competitor_low'] !== null ? (int) floor(Money::p((string) $analysis['competitor_low']) * (1 - min(20, max(0, (float) $rule->competitor_adjustment_percent)) / 100)) : null,
            'COMPETITOR_PREMIUM' => $analysis['competitor_low'] !== null ? (int) ceil(Money::p((string) $analysis['competitor_low']) * (1 + min(20, max(0, (float) $rule->competitor_adjustment_percent)) / 100)) : null,
            default => $analysis['target_price'],
        };
        if ($candidate === null || $candidate <= 0) return false;

        $floor = $rule->floor_price !== null ? Money::p((string) $rule->floor_price) : 0;
        $candidate = max($candidate, $minimumSafe, $floor);
        if ($rule->ceiling_price !== null && $candidate > Money::p((string) $rule->ceiling_price)) return false;
        $current = $analysis['current'];
        if ($current !== null && abs($candidate - $current) < 100) return false; // ignore changes below ₹1

        $proposedAnalysis = $this->pricing->analyze($product, $marketplace, Money::m($candidate));
        if (($proposedAnalysis['at']['margin'] ?? -INF) + 0.0001 < (float) $rule->minimum_margin_percent) {
            // Target-margin recommendation is the safe fallback; never lower the configured margin.
            $candidate = max((int) ($analysis['target_price'] ?? 0), $minimumSafe, $floor);
            if ($candidate <= 0 || ($rule->ceiling_price !== null && $candidate > Money::p((string) $rule->ceiling_price))) return false;
            $proposedAnalysis = $this->pricing->analyze($product, $marketplace, Money::m($candidate));
            if (($proposedAnalysis['at']['margin'] ?? -INF) + 0.0001 < (float) $rule->minimum_margin_percent) return false;
        }

        return DB::transaction(function () use ($rule, $product, $marketplace, $analysis, $proposedAnalysis, $candidate): bool {
            $exists = PricingRecommendation::query()->where('product_id', $product->id)->where('marketplace_id', $marketplace->id)->whereIn('status', ['pending', 'approved'])->lockForUpdate()->exists();
            if ($exists) return false;
            PricingRecommendation::create([
                'business_id' => $rule->business_id,
                'product_id' => $product->id,
                'marketplace_id' => $marketplace->id,
                'current_price' => $analysis['current'] !== null ? Money::m($analysis['current']) : null,
                'proposed_price' => Money::m($candidate),
                'minimum_price' => Money::m(max($analysis['min_price'], $rule->floor_price !== null ? Money::p((string) $rule->floor_price) : 0)),
                'expected_profit' => isset($proposedAnalysis['at']['profit']) ? Money::m((int) round($proposedAnalysis['at']['profit'])) : null,
                'expected_margin' => $proposedAnalysis['at']['margin'] ?? null,
                'reason' => 'Dynamic rule '.$rule->id.' ('.$rule->strategy.') — approval required',
                'status' => 'pending',
                'requested_by' => $rule->created_by,
            ]);
            return true;
        });
    }
}
