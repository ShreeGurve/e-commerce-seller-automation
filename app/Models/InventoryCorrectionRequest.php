<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCorrectionRequest extends Model
{
    protected $fillable = [
        'product_id', 'requested_by', 'reviewed_by', 'status', 'system_qty',
        'counted_qty', 'variance', 'note', 'review_note', 'approved_at',
        'applied_at', 'inventory_transaction_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function transaction() { return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id'); }
}
