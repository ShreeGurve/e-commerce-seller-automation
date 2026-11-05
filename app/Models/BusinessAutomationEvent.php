<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessAutomationEvent extends Model {
 protected $fillable=['rule_id','event_key','severity','title','message','source_type','source_id','event_fingerprint','status','resolved_at','created_by','assigned_to','assigned_at','due_at','priority'];
 protected $casts=['resolved_at'=>'datetime','assigned_at'=>'datetime','due_at'=>'datetime'];
 public function rule(){return $this->belongsTo(BusinessAutomationRule::class,'rule_id');}
 public function assignee(){return $this->belongsTo(User::class,'assigned_to');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
}
