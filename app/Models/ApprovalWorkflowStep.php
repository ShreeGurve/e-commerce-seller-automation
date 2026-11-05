<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApprovalWorkflowStep extends Model {
    protected $fillable=['approval_request_id','step_number','status','required_permission','reviewed_by','reviewed_at','decision_note'];
    protected $casts=['reviewed_at'=>'datetime'];
    public function approvalRequest(){return $this->belongsTo(ApprovalRequest::class);}
    public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
}
