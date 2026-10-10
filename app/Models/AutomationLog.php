<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class AutomationLog extends Model {protected $fillable=['automation_rule_id','fingerprint','severity','title','message','target_url','notified_at'];protected $casts=['notified_at'=>'datetime'];public function rule(){return $this->belongsTo(AutomationRule::class,'automation_rule_id');}}
