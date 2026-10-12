<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SettlementItem extends Model
{
    const UPDATED_AT = null;
    public const FEE_KINDS = ['REFERRAL_FEE', 'CLOSING_FEE', 'SHIPPING_FEE', 'COLLECTION_FEE', 'PAYMENT_FEE', 'OTHER_FEE'];
    public const KINDS = [
        'SALE' => 'Sale', 'SHIPPING_CREDIT' => 'Shipping charged', 'TAX' => 'Tax (pass-through)', 'REFUND' => 'Refund',
        'REFERRAL_FEE' => 'Referral fee', 'CLOSING_FEE' => 'Closing fee', 'SHIPPING_FEE' => 'Shipping fee', 'COLLECTION_FEE' => 'Collection fee',
        'PAYMENT_FEE' => 'Payment fee', 'OTHER_FEE' => 'Other fee', 'FEE_TAX' => 'GST on fees', 'REIMBURSEMENT' => 'Reimbursement',
        'ADJUSTMENT' => 'Adjustment', 'OTHER' => 'Other',
    ];
    protected $fillable = ['settlement_id', 'order_id', 'order_item_id', 'external_order_id', 'sku', 'kind', 'description', 'amount', 'posted_date', 'row_hash'];
    protected $casts = ['amount' => 'decimal:2', 'posted_date' => 'date'];
    public function settlement() { return $this->belongsTo(Settlement::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
