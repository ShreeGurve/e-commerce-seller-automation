<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierReturn extends Model
{
    protected $fillable = [
        'return_number', 'purchase_id', 'purchase_item_id', 'quality_inspection_id',
        'return_date', 'quantity', 'reason', 'resolution', 'status', 'supplier_reference',
        'dispatched_at', 'resolved_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'return_date' => 'date', 'dispatched_at' => 'datetime', 'resolved_at' => 'datetime',
    ];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function item() { return $this->belongsTo(PurchaseItem::class, 'purchase_item_id'); }
    public function inspection() { return $this->belongsTo(PurchaseQualityInspection::class, 'quality_inspection_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function settlements() { return $this->hasMany(SupplierReturnSettlement::class, 'supplier_return_id'); }
}
