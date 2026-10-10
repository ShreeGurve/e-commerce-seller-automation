<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'received_qty', 'unit_cost', 'gst_rate', 'line_subtotal', 'gst_amount', 'line_total'];
    protected $casts = ['unit_cost' => 'decimal:2', 'gst_rate' => 'decimal:2', 'line_subtotal' => 'decimal:2', 'gst_amount' => 'decimal:2', 'line_total' => 'decimal:2'];
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
}
