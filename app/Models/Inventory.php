<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';
    protected $fillable = ['product_id', 'minimum_stock', 'reorder_level']; // stock buckets change ONLY via InventoryService
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function isOut(): bool { return $this->available_qty <= 0; }
    public function isLow(): bool { return $this->minimum_stock > 0 && $this->available_qty <= $this->minimum_stock; }
}
