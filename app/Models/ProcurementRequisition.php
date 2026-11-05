<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProcurementRequisition extends Model {
 protected $fillable=['request_number','title','purpose','required_by','priority','status','requested_by','approved_by','approved_at','approval_notes','awarded_quote_id','awarded_by','awarded_at','award_reason'];
 protected $casts=['required_by'=>'date','approved_at'=>'datetime','awarded_at'=>'datetime'];
 public function items(){return $this->hasMany(ProcurementRequisitionItem::class,'requisition_id');}
 public function quotes(){return $this->hasMany(ProcurementSupplierQuote::class,'requisition_id');}
 public function awardedQuote(){return $this->belongsTo(ProcurementSupplierQuote::class,'awarded_quote_id');}
}
