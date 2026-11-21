<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationTaskDependency extends Model
{
    protected $fillable = ['predecessor_task_id', 'dependent_task_id', 'created_by'];

    public function predecessor()
    {
        return $this->belongsTo(AutomationTask::class, 'predecessor_task_id');
    }

    public function dependent()
    {
        return $this->belongsTo(AutomationTask::class, 'dependent_task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
