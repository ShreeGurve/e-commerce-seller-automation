<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class AutomationRule extends Model {protected $fillable=['name','trigger_key','threshold','is_active','last_run_at','created_by'];protected $casts=['threshold'=>'decimal:2','is_active'=>'boolean','last_run_at'=>'datetime'];public const TRIGGERS=['low_stock'=>'Low stock','claim_deadline'=>'Claim deadline approaching','negative_profit'=>'Negative order-line profit','high_acos'=>'Advertising ACOS above threshold'];public function logs(){return $this->hasMany(AutomationLog::class);}}
