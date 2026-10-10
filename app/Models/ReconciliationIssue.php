<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ReconciliationIssue extends Model
{
    public const TYPES = [
        'MISSING_SETTLEMENT' => 'Missing settlement', 'FEE_MISMATCH' => 'Unexpected fee', 'SALE_MISMATCH' => 'Incorrect amount',
        'REFUND_MISMATCH' => 'Refund mismatch', 'UNKNOWN_ORDER' => 'Settlement line with no order', 'CLAIM_UNPAID' => 'Approved claim not paid',
    ];
    protected $fillable = ['issue_type', 'order_id', 'settlement_id', 'external_order_id', 'expected_amount', 'actual_amount', 'difference', 'status', 'message',
        'fingerprint', 'note', 'resolved_by', 'detected_at', 'resolved_at'];
    protected $casts = ['expected_amount' => 'decimal:2', 'actual_amount' => 'decimal:2', 'difference' => 'decimal:2', 'detected_at' => 'datetime', 'resolved_at' => 'datetime'];
    public function order() { return $this->belongsTo(Order::class); }
    public function settlement() { return $this->belongsTo(Settlement::class); }
}
