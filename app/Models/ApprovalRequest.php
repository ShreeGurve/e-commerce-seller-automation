<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $guarded = [];
    protected $casts = [
        'current_value' => 'array', 'proposed_value' => 'array',
        'reviewed_at' => 'datetime', 'executed_at' => 'datetime',
    ];
    public function entity() { return $this->morphTo(); }
}
