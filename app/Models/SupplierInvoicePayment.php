<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierInvoicePayment extends Model {
    protected $fillable = ['purchase_invoice_match_id','amount','payment_date','payment_method','reference_number','status','notes','created_by'];
    protected $casts = ['amount'=>'decimal:2','payment_date'=>'date'];
    public function invoiceMatch(){ return $this->belongsTo(PurchaseInvoiceMatch::class,'purchase_invoice_match_id'); }
    public function creator(){ return $this->belongsTo(User::class,'created_by'); }
}
