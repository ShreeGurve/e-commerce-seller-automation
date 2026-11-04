<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInteraction extends Model
{
    protected $fillable = [
        'supplier_id', 'type', 'subject', 'details', 'reference_number',
        'due_date', 'status', 'created_by', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
