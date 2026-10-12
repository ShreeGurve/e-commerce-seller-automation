<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductMarketplace extends Model
{
    protected $table = 'product_marketplace';
    protected $fillable = ['product_id', 'product_variant_id', 'marketplace_id', 'marketplace_sku',
        'external_product_id', 'fulfillment_sku', 'listing_status'];
    public function product() { return $this->belongsTo(Product::class); }
    public function marketplace() { return $this->belongsTo(Marketplace::class); }
}
