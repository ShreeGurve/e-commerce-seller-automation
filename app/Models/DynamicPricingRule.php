<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicPricingRule extends Model
{
    protected $fillable = ['business_id', 'product_id', 'marketplace_id', 'strategy', 'floor_price', 'ceiling_price', 'minimum_margin_percent', 'competitor_adjustment_percent', 'enabled', 'created_by'];

    protected $casts = ['floor_price' => 'decimal:2', 'ceiling_price' => 'decimal:2', 'minimum_margin_percent' => 'decimal:2', 'competitor_adjustment_percent' => 'decimal:2', 'enabled' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }
}
