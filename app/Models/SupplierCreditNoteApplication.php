<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierCreditNoteApplication extends Model {
    protected $fillable = ['supplier_credit_note_id','purchase_invoice_match_id','amount','applied_date','notes','created_by'];
    protected $casts = ['amount'=>'decimal:2','applied_date'=>'date'];
    public function creditNote(){ return $this->belongsTo(SupplierCreditNote::class,'supplier_credit_note_id'); }
    public function invoiceMatch(){ return $this->belongsTo(PurchaseInvoiceMatch::class,'purchase_invoice_match_id'); }
}
