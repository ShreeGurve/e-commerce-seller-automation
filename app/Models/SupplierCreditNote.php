<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierCreditNote extends Model {
    protected $fillable = ['supplier_id','purchase_invoice_match_id','supplier_return_id','credit_note_number','credit_date','credit_amount','status','notes','created_by'];
    protected $casts = ['credit_date'=>'date','credit_amount'=>'decimal:2'];
    public function supplier(){ return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function invoiceMatch(){ return $this->belongsTo(PurchaseInvoiceMatch::class,'purchase_invoice_match_id'); }
    public function supplierReturn(){ return $this->belongsTo(SupplierReturn::class); }
    public function applications(){ return $this->hasMany(SupplierCreditNoteApplication::class); }
}
