<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRecurringTask extends Model
{
    protected $fillable = ['business_id', 'title', 'description', 'assigned_to', 'created_by', 'priority', 'frequency', 'interval_value', 'next_due_at', 'is_active'];

    protected $casts = ['next_due_at' => 'datetime', 'is_active' => 'boolean'];

    public function tasks()
    {
        return $this->hasMany(AutomationTask::class, 'recurring_template_id');
    }
}
