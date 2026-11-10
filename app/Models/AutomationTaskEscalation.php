<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationTaskEscalation extends Model
{
    protected $fillable = ['task_id', 'from_user_id', 'to_user_id', 'escalated_by', 'reason', 'created_at'];
    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    public function task() { return $this->belongsTo(AutomationTask::class, 'task_id'); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser() { return $this->belongsTo(User::class, 'to_user_id'); }
    public function escalatedBy() { return $this->belongsTo(User::class, 'escalated_by'); }
}
