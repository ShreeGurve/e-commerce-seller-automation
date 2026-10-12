<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'external_line_id', 'marketplace_sku', 'quantity', 'qty_deducted', 'unit_price', 'discount',
        'gst_rate', 'tax_amount', 'line_total', 'cogs_at_sale', 'packaging_at_sale', 'other_cost_at_sale'];
    protected $casts = ['unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'gst_rate' => 'decimal:2', 'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2', 'cogs_at_sale' => 'decimal:2', 'packaging_at_sale' => 'decimal:2', 'other_cost_at_sale' => 'decimal:2'];
    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function returnItems() { return $this->hasMany(ReturnItem::class); }
    public function unitCost(): float { return (float) $this->cogs_at_sale + (float) $this->packaging_at_sale + (float) $this->other_cost_at_sale; }
}
