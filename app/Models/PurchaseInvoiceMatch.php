<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceMatch extends Model
{
    protected $fillable = [
        'purchase_id', 'supplier_invoice_number', 'supplier_invoice_date',
        'invoice_subtotal', 'invoice_tax', 'invoice_shipping', 'invoice_other',
        'invoice_total', 'tolerance_amount', 'variance_amount', 'match_status',
        'review_notes', 'reviewed_by', 'reviewed_at', 'created_by',
        'supplier_gstin', 'buyer_gstin', 'place_of_supply', 'document_path', 'document_original_name',
        'document_mime', 'document_size', 'extracted_text', 'document_processing_status',
        'gst_validation_status', 'gst_validation_notes',
    ];

    protected $casts = [
        'supplier_invoice_date' => 'date',
        'invoice_subtotal' => 'decimal:2', 'invoice_tax' => 'decimal:2',
        'invoice_shipping' => 'decimal:2', 'invoice_other' => 'decimal:2',
        'invoice_total' => 'decimal:2', 'tolerance_amount' => 'decimal:2',
        'variance_amount' => 'decimal:2', 'reviewed_at' => 'datetime',
    ];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function lineChecks() { return $this->hasMany(PurchaseInvoiceLineCheck::class, 'purchase_invoice_match_id'); }
    public function payments() { return $this->hasMany(SupplierInvoicePayment::class, 'purchase_invoice_match_id'); }
    public function creditApplications() { return $this->hasMany(SupplierCreditNoteApplication::class, 'purchase_invoice_match_id'); }
}
