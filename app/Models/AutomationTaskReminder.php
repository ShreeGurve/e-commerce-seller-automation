<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationTaskReminder extends Model
{
    protected $fillable = ['task_id','user_id','reminder_type','reminded_at'];
    protected $casts = ['reminded_at'=>'datetime'];
    public function task() { return $this->belongsTo(AutomationTask::class, 'task_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
