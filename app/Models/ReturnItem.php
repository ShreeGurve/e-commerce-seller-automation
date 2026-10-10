<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    public const CONDITIONS = ['SELLABLE' => 'Sellable', 'USED' => 'Used', 'DAMAGED' => 'Damaged', 'SCRAP' => 'Scrap', 'MISSING' => 'Missing'];
    protected $fillable = ['return_id', 'order_item_id', 'product_id', 'quantity', 'received_qty', 'condition', 'restocked_qty', 'note'];
    public function productReturn() { return $this->belongsTo(ProductReturn::class, 'return_id'); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
}
