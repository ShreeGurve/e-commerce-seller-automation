<?php
namespace App\Services;
use App\Models\Product;
class PricingIntelligenceService {
 public function calculate(Product $product, array $input): array {
  $cogs=(float)$product->currentCost('COGS'); $pack=(float)$product->currentCost('PACKAGING');
  $other=(float)($input['other_unit_cost']??0); $cost=$cogs+$pack+$other;
  $fee=max(0,min(100,(float)$input['marketplace_fee_percent'])); $ads=max(0,min(100,(float)$input['ad_cost_percent'])); $margin=max(0,min(90,(float)$input['target_margin_percent']));
  $retained=1-(($fee+$ads)/100); $breakEven=$retained>0?$cost/$retained:0; $denominator=$retained-($margin/100); $target=$denominator>0?$cost/$denominator:0;
  return ['estimated_unit_cost'=>round($cost,2),'break_even_price'=>round($breakEven,2),'target_price'=>round($target,2),'selling_price'=>round((float)($input['selling_price']??$product->default_selling_price??0),2),'marketplace_fee_percent'=>$fee,'ad_cost_percent'=>$ads,'target_margin_percent'=>$margin,'estimated_profit'=>round(((float)($input['selling_price']??$product->default_selling_price??0))*$retained-$cost,2)];
 }
}
