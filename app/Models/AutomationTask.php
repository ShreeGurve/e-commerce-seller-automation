<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationTask extends Model
{
    protected $fillable = ['business_id', 'title', 'description', 'assigned_to', 'created_by', 'priority', 'status', 'due_at', 'completed_at', 'recurring_template_id', 'reminder_sent_at'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime', 'reminder_sent_at' => 'datetime'];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recurringTemplate()
    {
        return $this->belongsTo(AutomationRecurringTask::class, 'recurring_template_id');
    }

    public function reminders()
    {
        return $this->hasMany(AutomationTaskReminder::class, 'task_id');
    }
}
