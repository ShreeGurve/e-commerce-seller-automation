<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationExecution extends Model
{
    protected $fillable = [
        'scope', 'status', 'started_at', 'finished_at', 'duration_ms',
        'rules_count', 'matched_count', 'created_count', 'error_message',
        'retry_of_id', 'triggered_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function retryOf()
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }
}
