<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceLineCheck extends Model
{
    protected $fillable = [
        'purchase_invoice_match_id', 'purchase_item_id', 'invoice_quantity',
        'invoice_unit_cost', 'invoice_line_total', 'quantity_variance',
        'cost_variance', 'line_status', 'review_note',
    ];

    protected $casts = [
        'invoice_unit_cost' => 'decimal:2',
        'invoice_line_total' => 'decimal:2',
        'cost_variance' => 'decimal:2',
    ];

    public function invoiceMatch()
    {
        return $this->belongsTo(PurchaseInvoiceMatch::class, 'purchase_invoice_match_id');
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
