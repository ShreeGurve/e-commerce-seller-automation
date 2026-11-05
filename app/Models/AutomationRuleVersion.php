<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRuleVersion extends Model
{
    protected $fillable = [
        'automation_rule_id', 'version_number', 'snapshot', 'change_type',
        'change_summary', 'changed_by',
    ];

    protected $casts = ['snapshot' => 'array'];

    public function rule()
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
