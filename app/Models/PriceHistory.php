<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PriceHistory extends Model { protected $fillable=['product_id','selling_price','break_even_price','target_price','estimated_unit_cost','marketplace_fee_percent','ad_cost_percent','target_margin_percent','effective_from','created_by']; protected $casts=['effective_from'=>'date']; public function product(){return $this->belongsTo(Product::class);} }
