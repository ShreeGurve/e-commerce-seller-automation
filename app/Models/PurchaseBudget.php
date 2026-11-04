<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBudget extends Model
{
    protected $fillable = ['budget_month', 'budget_amount', 'notes', 'created_by', 'updated_by'];

    protected $casts = [
        'budget_month' => 'date',
        'budget_amount' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
