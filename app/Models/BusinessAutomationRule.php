<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessAutomationRule extends Model {
 protected $fillable=['rule_key','name','description','threshold_value','is_enabled','last_evaluated_at'];
 protected $casts=['threshold_value'=>'decimal:3','is_enabled'=>'boolean','last_evaluated_at'=>'datetime'];
 public function events(){return $this->hasMany(BusinessAutomationEvent::class,'rule_id');}
}
