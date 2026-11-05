<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SmartReorderRun extends Model {
    protected $fillable = ['run_number','products_considered','items_added','requisition_id','generated_by','notes'];
    public function requisition(){ return $this->belongsTo(ProcurementRequisition::class,'requisition_id'); }
    public function generator(){ return $this->belongsTo(User::class,'generated_by'); }
}
