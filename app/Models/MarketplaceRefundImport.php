<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceRefundImport extends Model
{
    protected $fillable = ['marketplace_id', 'external_refund_id', 'external_order_id', 'external_return_id', 'amount', 'currency', 'refund_date', 'reason', 'status', 'refund_id', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected $casts = ['amount' => 'decimal:2', 'refund_date' => 'date', 'reviewed_at' => 'datetime'];

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }
}
