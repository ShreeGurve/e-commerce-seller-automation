<?php
namespace App\Services;

use App\Models\Product;

/**
 * Legacy, side-effect-free pricing calculator.
 * Monetary values are expressed in the same currency units as Product::currentCost().
 */
class PricingIntelligenceService
{
    public function calculate(Product $product, array $input): array
    {
        $cogs = (float) $product->currentCost('COGS');
        $other = (float) $product->currentCost('OTHER');
        $fixed = $cogs + $other + (float) ($input['other_unit_cost'] ?? 0);
        $feeRate = max(0, (float) ($input['marketplace_fee_percent'] ?? 0)) / 100;
        $adRate = max(0, (float) ($input['ad_cost_percent'] ?? 0)) / 100;
        $marginRate = max(0, min(99.99, (float) ($input['target_margin_percent'] ?? 0))) / 100;
        $selling = max(0, (float) ($input['selling_price'] ?? 0));

        $breakEvenDenominator = 1 - $feeRate - $adRate;
        $targetDenominator = 1 - $feeRate - $adRate - $marginRate;
        $breakEven = $breakEvenDenominator > 0 ? round($fixed / $breakEvenDenominator, 2) : null;
        $target = $targetDenominator > 0 ? round($fixed / $targetDenominator, 2) : null;
        $profit = $selling - $fixed - ($selling * $feeRate) - ($selling * $adRate);

        return [
            'break_even_price' => $breakEven,
            'target_price' => $target,
            'estimated_profit' => round($profit, 2),
            'estimated_margin_percent' => $selling > 0 ? round($profit / $selling * 100, 2) : null,
        ];
    }
}
