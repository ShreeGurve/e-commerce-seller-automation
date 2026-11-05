<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApprovalWorkflowPolicy extends Model {
    protected $fillable=['request_type','min_amount','max_amount','approval_levels','reviewer_permission','is_enabled'];
    protected $casts=['min_amount'=>'decimal:2','max_amount'=>'decimal:2','approval_levels'=>'integer','is_enabled'=>'boolean'];
}
