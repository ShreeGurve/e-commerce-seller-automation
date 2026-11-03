<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceProductSetting extends Model
{
    protected $fillable = [
        'product_id', 'marketplace_id', 'outbound_shipping_cost', 'handling_cost',
        'desired_profit_min', 'desired_profit_max', 'manual_selling_price',
        'auto_price_enabled', 'push_enabled',
    ];

    protected $casts = [
        'outbound_shipping_cost' => 'decimal:2',
        'handling_cost' => 'decimal:2',
        'desired_profit_min' => 'decimal:2',
        'desired_profit_max' => 'decimal:2',
        'manual_selling_price' => 'decimal:2',
        'auto_price_enabled' => 'boolean',
        'push_enabled' => 'boolean',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
}
