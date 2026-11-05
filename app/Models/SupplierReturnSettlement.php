<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierReturnSettlement extends Model
{
    protected $fillable = [
        'supplier_return_id', 'settlement_type', 'amount', 'replacement_quantity',
        'reference_number', 'settlement_date', 'status', 'notes', 'created_by', 'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'settlement_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function supplierReturn()
    {
        return $this->belongsTo(SupplierReturn::class, 'supplier_return_id');
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function confirmer() { return $this->belongsTo(User::class, 'confirmed_by'); }
}
