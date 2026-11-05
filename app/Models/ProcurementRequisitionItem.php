<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProcurementRequisitionItem extends Model {
 protected $fillable=['requisition_id','item_name','sku','quantity','unit','estimated_unit_cost','specifications'];
 protected $casts=['quantity'=>'decimal:3','estimated_unit_cost'=>'decimal:2'];
 public function requisition(){return $this->belongsTo(ProcurementRequisition::class,'requisition_id');}
}
