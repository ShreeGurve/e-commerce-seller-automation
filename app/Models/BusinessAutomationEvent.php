<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessAutomationEvent extends Model {
 protected $fillable=['rule_id','event_key','severity','title','message','source_type','source_id','event_fingerprint','status','resolved_at','created_by'];
 protected $casts=['resolved_at'=>'datetime'];
 public function rule(){return $this->belongsTo(BusinessAutomationRule::class,'rule_id');}
}
