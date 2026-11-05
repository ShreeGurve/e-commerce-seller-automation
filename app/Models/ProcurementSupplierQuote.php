<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProcurementSupplierQuote extends Model {
 protected $fillable=['requisition_id','supplier_id','quote_reference','quote_date','valid_until','shipping_cost','tax_amount','other_cost','quoted_total','lead_time_days','status','terms','notes','recorded_by'];
 protected $casts=['quote_date'=>'date','valid_until'=>'date','shipping_cost'=>'decimal:2','tax_amount'=>'decimal:2','other_cost'=>'decimal:2','quoted_total'=>'decimal:2'];
 public function requisition(){return $this->belongsTo(ProcurementRequisition::class,'requisition_id');}
 public function supplier(){return $this->belongsTo(Supplier::class)->withTrashed();}
 public function items(){return $this->hasMany(ProcurementSupplierQuoteItem::class,'quote_id');}
}
