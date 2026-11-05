<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseQualityInspection extends Model
{
    protected $fillable = [
        'purchase_id', 'purchase_item_id', 'inspection_date', 'inspected_qty',
        'accepted_qty', 'rejected_qty', 'defect_type', 'supplier_action',
        'status', 'notes', 'created_by',
    ];

    protected $casts = ['inspection_date' => 'date'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function item() { return $this->belongsTo(PurchaseItem::class, 'purchase_item_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
