<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStockCount extends Model
{
    protected $fillable = ['product_id', 'system_qty', 'counted_qty', 'variance', 'note', 'counted_by', 'counted_at'];
    protected $casts = ['counted_at' => 'datetime'];

    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function user() { return $this->belongsTo(User::class, 'counted_by'); }
}
