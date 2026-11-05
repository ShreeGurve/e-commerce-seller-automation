<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProcurementSupplierQuoteItem extends Model {
 protected $fillable=['quote_id','requisition_item_id','unit_cost','quantity','line_total','availability','notes'];
 protected $casts=['unit_cost'=>'decimal:2','quantity'=>'decimal:3','line_total'=>'decimal:2'];
 public function quote(){return $this->belongsTo(ProcurementSupplierQuote::class,'quote_id');}
 public function requisitionItem(){return $this->belongsTo(ProcurementRequisitionItem::class,'requisition_item_id');}
}
