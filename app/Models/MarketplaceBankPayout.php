<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceBankPayout extends Model
{
    public const STATUSES = ['UNMATCHED', 'MATCHED', 'MISMATCH', 'IGNORED'];

    protected $fillable = [
        'marketplace_id', 'settlement_id', 'bank_reference', 'external_settlement_id', 'payout_date',
        'bank_amount', 'settlement_amount', 'difference', 'currency', 'status', 'note',
        'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'payout_date' => 'date', 'bank_amount' => 'decimal:2', 'settlement_amount' => 'decimal:2',
        'difference' => 'decimal:2', 'reviewed_at' => 'datetime',
    ];

    public function marketplace() { return $this->belongsTo(Marketplace::class); }
    public function settlement() { return $this->belongsTo(Settlement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
