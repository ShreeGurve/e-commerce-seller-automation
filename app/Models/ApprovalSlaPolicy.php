<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApprovalSlaPolicy extends Model {
 protected $fillable=['request_type','sla_hours','is_enabled'];
 protected $casts=['sla_hours'=>'integer','is_enabled'=>'boolean'];
}
